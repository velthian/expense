#!/usr/bin/env python3
"""
fetch_transactions.py

Replaces cliGetData.php and cliGetData2.php.
Reads HDFC alert emails via IMAP and writes parsed transactions to MySQL.

Usage:
    python3 fetch_transactions.py

Dependencies (pip install):
    pymysql
"""

import email
import email.header
import email.message
import email.utils
import imaplib
import logging
import re
import sys
import configparser
from datetime import datetime, timedelta
from pathlib import Path

import pymysql
import pymysql.cursors

# ── Logging ───────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s %(levelname)s %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
log = logging.getLogger(__name__)

SCRIPT_DIR = Path(__file__).parent


# ── Config & DB ───────────────────────────────────────────────────────────────

def load_config() -> configparser.ConfigParser:
    cfg = configparser.ConfigParser()
    cfg.read(SCRIPT_DIR / 'config.ini')
    return cfg


def db_connect(cfg: configparser.ConfigParser):
    db = cfg['db']
    return pymysql.connect(
        host=db['host'],
        port=int(db.get('port', 3306)),
        user=db['user'],
        password=db['password'],
        database=db['name'],
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


# ── DB operations ─────────────────────────────────────────────────────────────

def get_existing_uids(conn, loginid: str) -> set:
    with conn.cursor() as cur:
        cur.execute("SELECT uid FROM transactions WHERE username=%s", (loginid,))
        return {str(row['uid']) for row in cur.fetchall()}


def put_merchant(conn, merchant: str, loginid: str) -> bool:
    """Upsert merchant, maintaining the max_merchant_count sequence."""
    merchant = merchant.upper()
    with conn.cursor() as cur:
        cur.execute(
            "SELECT 1 FROM merchant WHERE merchant_name=%s AND username=%s",
            (merchant, loginid)
        )
        if cur.fetchone():
            return True  # already exists

        cur.execute(
            "SELECT max_merchant_id FROM max_merchant_count WHERE username=%s",
            (loginid,)
        )
        row = cur.fetchone()
        new_id = (int(row['max_merchant_id']) + 1) if row else 1

        if row:
            cur.execute(
                "UPDATE max_merchant_count SET max_merchant_id=%s WHERE username=%s",
                (new_id, loginid)
            )
        else:
            cur.execute(
                "INSERT INTO max_merchant_count (max_merchant_id, username) VALUES (%s, %s)",
                (new_id, loginid)
            )

        cur.execute(
            "INSERT INTO merchant (merchant_id, merchant_name, category_id, username) VALUES (%s, %s, '0', %s)",
            (new_id, merchant, loginid)
        )

    conn.commit()
    return True


def get_merchant_id(conn, merchant: str, loginid: str):
    merchant = merchant.upper()
    with conn.cursor() as cur:
        cur.execute(
            "SELECT merchant_id FROM merchant WHERE merchant_name=%s AND username=%s",
            (merchant, loginid)
        )
        row = cur.fetchone()
        return str(row['merchant_id']) if row else None


def put_transaction(conn, uid: str, merchant_id: str, amount: float,
                    date: str, mode: str, loginid: str) -> bool:
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO transactions (uid, merchant_id, amount, date, mode, username) "
                "VALUES (%s, %s, %s, %s, %s, %s)",
                (uid, merchant_id, amount, date, mode, loginid)
            )
        conn.commit()
        return True
    except Exception as e:
        conn.rollback()
        log.error(f"put_transaction failed: {e}")
        return False


# ── Email parsing ─────────────────────────────────────────────────────────────

def _strip_html(raw: str) -> str:
    import html
    # Remove style/script blocks entirely so CSS rules don't pollute the text
    text = re.sub(r'<style[^>]*>.*?</style>', '', raw, flags=re.IGNORECASE | re.DOTALL)
    text = re.sub(r'<script[^>]*>.*?</script>', '', text, flags=re.IGNORECASE | re.DOTALL)
    text = html.unescape(re.sub(r'<[^>]+>', '', text))
    # Collapse whitespace
    return re.sub(r'\s+', ' ', text).strip()


def parse_hdfc_cc_new(body: str) -> dict:
    """Port of parseHdfcCcEmail() — handles current HDFC CC email format."""
    message = _strip_html(body)
    message = re.sub(r'^.*?(Dear Customer\b)', r'\1', message,
                     flags=re.IGNORECASE | re.DOTALL)
    message = re.sub(r'\s+', ' ', message).strip()

    pattern = (
        r'(?:Rs\.?|INR|₹)\s*(?P<amount>[0-9][0-9,]*(?:\.\d{1,2})?)'
        r'\s+is\s+debited\s+from\s+your\s+HDFC\s+Bank\s+Credit\s+Card'
        r'(?:\s+ending\s+(?P<last4>\d{4}))?'
        r'\s+(?:towards|at)\s+(?P<merchant>.+?)'
        r'\s+on\s+(?P<date>\d{1,2}\s+[A-Za-z]{3,9},?\s+\d{4})'
        r'\s+at\s+(?P<time>\d{1,2}:\d{2}(?::\d{2})?\s*(?:[AP]M)?)\b'
    )
    m = re.search(pattern, message, re.IGNORECASE)
    if not m:
        return {'ok': False, 'error': 'Pattern not matched'}

    amount = float(m.group('amount').replace(',', ''))
    merchant = re.sub(r'\s+', ' ', m.group('merchant')).strip(' \t\n\r.-')
    return {'ok': True, 'amount': amount, 'merchant': merchant}


def parse_hdfc_cc_old(body: str) -> dict:
    """Port of parseHdfcCcEmailOldFormat() — handles older HDFC CC email format."""
    text = re.sub(r'\s+', ' ', body).strip()
    out = {'amount': None, 'merchant': None}

    m = re.search(r'\b(?:Rs\.?|Re\.?)\s*([0-9][0-9,]*(?:\.\d{1,2})?)', text, re.IGNORECASE)
    if m:
        out['amount'] = float(m.group(1).replace(',', ''))

    m = re.search(
        r'\b(?:at|towards)\s+(.+?)\s+on\s+(\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2})',
        text, re.IGNORECASE
    )
    if m:
        out['merchant'] = m.group(1).strip()

    return out


def parse_email_body(subject: str, body: str) -> dict:
    """
    Classify and extract transaction details from an HDFC alert email body.

    Returns:
        {'flag': str, 'amount': float|None, 'merchant': str|None}
        flag values: creditcard | upi | netbanking | banktransfer | genskip
    """
    result = {'flag': 'genskip', 'amount': None, 'merchant': None}

    # Normalize HTML to plain text for all parsing paths
    body = _strip_html(body)

    # ── Credit Card (detected via subject) ───────────────────────────────────
    if re.search(r'Credit Card', subject, re.IGNORECASE):
        result['flag'] = 'creditcard'

        rev_pos = body.find('transaction reversal')
        if rev_pos != -1:
            # Reversal entry — amount is negative
            sub = body[rev_pos + 47:]
            amount_str = sub.split(', from ')[0].strip()
            try:
                amount = -float(amount_str.replace(',', ''))
            except ValueError:
                return result
            after = body[rev_pos + 47 + len(amount_str) + 7:]
            to_idx = after.lower().find(' to ')
            merchant = after[:to_idx].strip() if to_idx != -1 else ''
            result['amount'] = amount
            result['merchant'] = merchant
            return result

        # New format
        rv = parse_hdfc_cc_new(body)
        if rv['ok']:
            result['amount'] = rv['amount']
            result['merchant'] = rv['merchant']
            return result

        # Old format fallback
        log.warning("CC new-format parse failed, trying old format")
        rv2 = parse_hdfc_cc_old(body)
        result['amount'] = rv2['amount']
        result['merchant'] = rv2['merchant']
        if not rv2['amount']:
            result['flag'] = 'genskip'
        return result

    # ── UPI new format: "debited ... to VPA vpa@bank MERCHANT NAME on dd-mm-yy" ─
    if 'to VPA' in body:
        result['flag'] = 'upi'
        m = re.search(r'Rs\.?\s*([0-9,]+(?:\.\d{1,2})?)\s+has been debited', body, re.IGNORECASE)
        if not m:
            result['flag'] = 'genskip'
            return result
        result['amount'] = float(m.group(1).replace(',', ''))
        # Merchant name follows the VPA address (e.g. "to VPA foo@bar MERCHANT NAME on")
        m2 = re.search(r'to VPA\s+\S+\s+(.+?)\s+on\s+\d{2}-\d{2}-\d{2}\b', body, re.IGNORECASE)
        if not m2:
            result['flag'] = 'genskip'
            return result
        result['merchant'] = m2.group(1).strip()
        return result

    # ── UPI old format: body contains "UPI" keyword ───────────────────────────
    if 'UPI' in body:
        result['flag'] = 'upi'
        m = re.search(r'Rs\.\s*([0-9,]+(?:\.\d{1,2})?)\s+at\s+', body)
        if not m:
            result['flag'] = 'genskip'
            return result
        result['amount'] = float(m.group(1).replace(',', ''))
        m2 = re.search(r' to (.+?) on ', body)
        if not m2:
            result['flag'] = 'genskip'
            return result
        result['merchant'] = m2.group(1).strip()
        return result

    # ── Skip: CC bill payment (not a purchase) ────────────────────────────────
    if 'Credit Card Payment done using HDFC Bank Online Banking' in body:
        return result  # genskip

    # ── Skip: upcoming e-mandate notification (no debit yet) ─────────────────
    if 'upcoming E-mandate' in body:
        return result  # genskip

    # ── E-mandate completed: auto-payment charged to CC ──────────────────────
    if 'E-mandate' in body and 'successfully paid' in body:
        result['flag'] = 'creditcard'
        m = re.search(r'Amount:\s*INR\s*([0-9,]+(?:\.\d{1,2})?)', body, re.IGNORECASE)
        if not m:
            result['flag'] = 'genskip'
            return result
        result['amount'] = float(m.group(1).replace(',', ''))
        m2 = re.search(r'Your\s+(.+?)\s+bill', body, re.IGNORECASE)
        if not m2:
            result['flag'] = 'genskip'
            return result
        result['merchant'] = m2.group(1).strip()
        return result

    # ── Online banking transfer: "Transfer to payee MERCHANT via HDFC Bank Online Banking" ─
    if 'Transfer to payee' in body:
        result['flag'] = 'banktransfer'
        m = re.search(r'Rs\.?\s*([0-9,]+(?:\.\d{1,2})?)\s+has been deducted', body, re.IGNORECASE)
        if not m:
            result['flag'] = 'genskip'
            return result
        result['amount'] = float(m.group(1).replace(',', ''))
        m2 = re.search(r'Transfer to payee\s+(.+?)\s+via HDFC Bank Online Banking', body, re.IGNORECASE)
        if not m2:
            result['flag'] = 'genskip'
            return result
        result['merchant'] = m2.group(1).strip()
        return result

    # ── Bank Transfer (old format) ────────────────────────────────────────────
    if 'HDFC Bank A/c' in body:
        result['flag'] = 'banktransfer'
        pos = body.find('Amount deducted')
        if pos == -1:
            result['flag'] = 'genskip'
            return result
        sub = body[pos + 23:]
        amount_str = sub.split(' From ')[0].strip()
        try:
            result['amount'] = float(amount_str.replace(',', ''))
        except ValueError:
            result['flag'] = 'genskip'
            return result
        result['merchant'] = 'Bank Transfer - Payee Unknown'
        return result

    # ── NetBanking ────────────────────────────────────────────────────────────
    if 'NetBanking for payment of' in body:
        result['flag'] = 'netbanking'
        pos = body.find('Rs.')
        if pos == -1:
            result['flag'] = 'genskip'
            return result
        sub = body[pos + 4:]
        amount_str = sub.split(' from A/c')[0].strip()
        try:
            result['amount'] = float(amount_str.replace(',', ''))
        except ValueError:
            result['flag'] = 'genskip'
            return result
        to_pos = body.find(' to ')
        if to_pos == -1:
            result['flag'] = 'genskip'
            return result
        after_to = body[to_pos + 4:]
        not_you_pos = after_to.find('Not you?')
        if not_you_pos == -1:
            result['flag'] = 'genskip'
            return result
        result['merchant'] = after_to[:not_you_pos].strip()
        return result

    return result  # genskip


# ── IMAP helpers ──────────────────────────────────────────────────────────────

def get_email_body(msg: email.message.Message) -> str:
    """Extract decoded body from an email.Message. Prefers text/plain, falls back to text/html (stripped)."""
    plain = ''
    html_body = ''

    if msg.is_multipart():
        for part in msg.walk():
            content_type = part.get_content_type()
            disposition = str(part.get('Content-Disposition', ''))
            if 'attachment' in disposition:
                continue
            charset = part.get_content_charset() or 'utf-8'
            payload = part.get_payload(decode=True)
            if not payload:
                continue
            text = payload.decode(charset, errors='replace')
            if content_type == 'text/plain' and not plain:
                plain = text
            elif content_type == 'text/html' and not html_body:
                html_body = text
    else:
        charset = msg.get_content_charset() or 'utf-8'
        payload = msg.get_payload(decode=True)
        if payload:
            text = payload.decode(charset, errors='replace')
            if msg.get_content_type() == 'text/html':
                html_body = text
            else:
                plain = text

    if plain:
        return plain
    # HTML-only email — strip tags so regex parsers can work on it
    return _strip_html(html_body)


def decode_subject(raw: str) -> str:
    parts = email.header.decode_header(raw)
    decoded = ''
    for part, charset in parts:
        if isinstance(part, bytes):
            decoded += part.decode(charset or 'utf-8', errors='replace')
        else:
            decoded += part
    return decoded


# ── Core processing ───────────────────────────────────────────────────────────

def process_account(account_cfg, conn) -> None:
    imap_host = account_cfg['imap_host']
    imap_port = int(account_cfg['imap_port'])
    imap_user = account_cfg['username']
    imap_pass = account_cfg['password']
    loginid   = account_cfg['loginid']
    mailbox   = account_cfg.get('mailbox', 'INBOX')

    log.info(f"--- Account: {imap_user} (loginid: {loginid}) ---")

    existing_uids = get_existing_uids(conn, loginid)

    mail = imaplib.IMAP4_SSL(imap_host, imap_port)
    try:
        mail.login(imap_user, imap_pass)
        mail.select(mailbox)

        since = (datetime.now() - timedelta(days=20)).strftime('%d-%b-%Y')
        search_str = f'FROM "alerts@hdfcbank.bank.in" SINCE "{since}"'
        status, data = mail.uid('search', None, search_str)

        if status != 'OK' or not data[0]:
            log.info("No messages found.")
            return

        uids = data[0].split()
        log.info(f"Found {len(uids)} message(s) to check")

        for uid_bytes in uids:
            uid = uid_bytes.decode()

            if uid in existing_uids:
                log.info(f"UID {uid}: already in DB, skipping")
                continue

            # Try RFC822 first, fall back to BODY.PEEK[] (required by iCloud)
            raw = None
            for fetch_item in ['(RFC822)', '(BODY.PEEK[])']:
                status, msg_data = mail.uid('fetch', uid_bytes, fetch_item)
                if status != 'OK' or not msg_data:
                    continue
                for part in msg_data:
                    if isinstance(part, tuple) and isinstance(part[1], bytes):
                        raw = part[1]
                        break
                if raw:
                    break

            if raw is None:
                log.warning(f"UID {uid}: could not extract raw message")
                continue
            msg = email.message_from_bytes(raw)

            date_str = msg.get('Date', '')
            try:
                msg_date = email.utils.parsedate_to_datetime(date_str).strftime('%Y-%m-%d')
            except Exception:
                msg_date = datetime.now().strftime('%Y-%m-%d')

            subject = decode_subject(msg.get('Subject', ''))
            body = get_email_body(msg)

            log.info(f"UID {uid}: {subject}")

            parsed = parse_email_body(subject, body)

            if parsed['flag'] == 'genskip' or not parsed['merchant']:
                log.info(f"UID {uid}: no match, skipping")
                continue

            merchant = parsed['merchant']
            amount   = parsed['amount']
            flag     = parsed['flag']

            put_merchant(conn, merchant, loginid)
            merchant_id = get_merchant_id(conn, merchant, loginid)

            if not merchant_id:
                log.error(f"UID {uid}: could not get merchant_id for '{merchant}'")
                continue

            ok = put_transaction(conn, uid, merchant_id, amount, msg_date, flag, loginid)
            if ok:
                log.info(f"UID {uid}: saved — {merchant} | {amount} | {flag}")
            else:
                log.error(f"UID {uid}: failed to save transaction")

    finally:
        mail.logout()


def main() -> None:
    cfg = load_config()
    conn = db_connect(cfg)
    try:
        for section in cfg.sections():
            if section.startswith('account:'):
                process_account(cfg[section], conn)
    finally:
        conn.close()


if __name__ == '__main__':
    main()
