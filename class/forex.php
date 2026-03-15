<?php
class forex 
{
    public function getForexFromAPI($from, $to)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
                CURLOPT_URL => "https://alpha-vantage.p.rapidapi.com/query?to_currency=". $to . "&function=CURRENCY_EXCHANGE_RATE&from_currency=" . $from,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                        "X-RapidAPI-Host: alpha-vantage.p.rapidapi.com",
                        "X-RapidAPI-Key: 5KREwfc2l1mshwMpQtXxduMtSSShp1GiXvgjsnCWHZW05wnhyF"
                ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) 
        {
            return false;
        } 
        else 
        {
            $r = array();
            $r = json_decode($response, TRUE);
            $exchange_rate = '';
            if (isset($r['Realtime Currency Exchange Rate']['5. Exchange Rate'])) 
            {
                $exchange_rate = $r['Realtime Currency Exchange Rate']['5. Exchange Rate'];
                $result = floatval($exchange_rate); // Ensure it's numeric
                return $result;
            } 
            else 
            {
                return false;
            }
        }
    }
}
?>