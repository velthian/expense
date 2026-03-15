<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Drag & Match A → B</title>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
  <style>
    body { font-family: system-ui, Arial, sans-serif; margin: 24px; }
    .wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .col { border: 1px solid #ddd; border-radius: 10px; padding: 12px; }
    .col h3 { margin: 0 0 12px; font-size: 1rem; color: #333; }
    ul { list-style: none; margin: 0; padding: 0; }
    li { padding: 10px 12px; margin: 8px 0; background: #f7f7f7; border-radius: 8px; border: 1px solid #e5e5e5; cursor: grab; }
    .b-item { cursor: default; }
    .ui-draggable-dragging { opacity: 0.85; }
    .droppable-hover { outline: 2px dashed #888; background: #fffef0; }
    .matched { background: #e7f8ef; border-color: #bfead1; position: relative; }
    .matched::after { content: "✓"; position: absolute; right: 10px; top: 8px; color: #0a7a3c; font-weight: 700; }
    .disabled { opacity: 0.5; pointer-events: none; }
    .pairs { margin-top: 20px; font-size: 0.95rem; }
    code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>

<div class="wrap">
  <div class="col" id="colA">
    <h3>Column A (drag these)</h3>
    <ul>
      <li class="a-item" data-id="A1">A1 — “Uber 256.78”</li>
      <li class="a-item" data-id="A2">A2 — “Zomato 899.00”</li>
      <li class="a-item" data-id="A3">A3 — “IRCTC 1430.50”</li>
    </ul>
  </div>
  <div class="col" id="colB">
    <h3>Column B (drop targets)</h3>
    <ul>
      <li class="b-item" data-id="B1">B1 — “UBER BV ₹256.78”</li>
      <li class="b-item" data-id="B2">B2 — “IRCTC LTD ₹1,430.50”</li>
      <li class="b-item" data-id="B3">B3 — “ZOMATO ₹899.00”</li>
    </ul>
  </div>
</div>

<div class="pairs">
  <strong>Mapped pairs:</strong>
  <div id="log"></div>
</div>

<script>
  // Keep track of which A or B items are already matched
  const matchedA = new Set();
  const matchedB = new Set();

  // Make A items draggable
  $('.a-item').draggable({
    helper: 'clone',          // drag a clone, leave original in place
    revert: 'invalid',        // snap back if dropped on a non-accepting target
    appendTo: 'body',
    start: function (_, ui) {
      // Optional: add a class to original for visual feedback
      $(this).addClass('dragging');
    },
    stop: function () {
      $(this).removeClass('dragging');
    }
  });

  // Make B items droppable
  $('.b-item').droppable({
    tolerance: 'intersect',
    hoverClass: 'droppable-hover',
    accept: function (draggable) {
      const aId = $(draggable).data('id');
      const bId = $(this).data('id');
      // Reject if either side already matched
      return !matchedA.has(aId) && !matchedB.has(bId);
    },
    drop: function (event, ui) {
      const $a = $(ui.draggable);     // original A element (not the helper clone)
      const $b = $(this);

      const aId = $a.data('id');
      const bId = $b.data('id');

      // Mark as matched (prevent further use)
      matchedA.add(aId);
      matchedB.add(bId);

      $a.addClass('matched disabled').draggable('disable');
      $b.addClass('matched disabled').droppable('disable');

      // Fire a custom jQuery event with the mapping payload
      // You can listen on document/body/app root as you prefer
      $(document).trigger('pair:mapped', {
        fromId: aId,
        toId: bId,
        fromText: $a.text(),
        toText: $b.text()
      });
    }
  });

  // Example listener where you can plug your reconciliation logic
  $(document).on('pair:mapped', function (e, data) {
    // Here you can call your AJAX/PHP endpoint to persist the mapping
    // $.post('/api/map', { from: data.fromId, to: data.toId });

    // Demo log
    $('#log').append(
      $('<div/>').text(`Mapped ${data.fromId} → ${data.toId} | ${data.fromText} ↔ ${data.toText}`)
    );
    console.log('Matched pair:', data);
  });
</script>
</body>
</html>