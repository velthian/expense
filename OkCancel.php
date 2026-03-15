<div id="OkCancel">
    <div id="OkCanMsg"><?php echo $msg;?></div>
    <div id="actions">
        <span id="Ok">OK</span>
        <span id="Cancel">Cancel</span>
    </div>
    <input id="OkCanFlag" type="hidden" value="0" />
</div>

<script>

$("#Ok").click(function(){
    $("#OkCanFlag").val("0");
});

$("#Cancel").click(function(){
    $("#OkCanFlag").val("1");
});
    
</script>