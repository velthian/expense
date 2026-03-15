<?php
include('header.php');
include('db.php');

$super_category_list = array();
$sql1 = "SELECT * FROM super_category";
$result1 = $conn->query($sql1);
while($row1 = $result1->fetch_assoc())
{
    $super_category_list[] = array('id' => $row1['super_category_id'], 'super_category_desc' => $row1['super_category_desc']);
}

$conn->close();
?>
<div id="categFilter">
    <select id="supCatOptions">
        <?php
        foreach($super_category_list as $sup)
        {
            echo("<option value='" . $sup['id'] . "'>" . $sup['super_category_desc'] . "</option>");
        }
        ?>
    </select>
    <button id="supCatListBtn">GO</button>
</div>
<div id="supCatDataCont">
    
</div>

<script>
    $('#supCatListBtn').click(function(){
    var supCatId = $('#supCatOptions').val();
    
        $.ajax({
            type: "POST",
            url: "getSupCatTxn.php",
            data: { supCatId: supCatId },
            success: function(response) {
                var result = JSON.parse(response);
                var html = "";

                $.each(result, function(fy, fyData) {

                    html += "<h3>FY " + fy + "</h3>";
                    html += "<div class='supCatAmt'>" + fyData.totalAmt + "</div>";

                    $.each(fyData.data, function(i, record) {
                        html +=
                            '<div class="recordBlock">' +
                                '<div class="bankRecord">' +
                                    '<div class="recordItem2">' + record.date + '</div>' +
                                    '<div class="recordItem2">' + record.merchant_name + '</div>' +
                                    '<div class="recordItem2">' + record.amount + '</div>' +
                                '</div>' +
                            '</div>';
                    });
                });

                $('#supCatDataCont').html(html);
            },

            error: function(xhr, status, error) {
                console.log("AJAX Error:", error);
            }
        });
    });

</script>
<?php
        
?>