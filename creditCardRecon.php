<?php
include('header.php');

$status = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $whatGotSubmitted = '';
    $whatGotSubmitted = $_POST['formType'];
    
    switch($whatGotSubmitted)
    {
        case("csvUpload"):
        {
            $goodToUpload = FALSE;
            $target_dir = __DIR__ . "/datafiles/";
            $source_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
            $fileType = strtolower(pathinfo($source_file,PATHINFO_EXTENSION));
            //CHECK IF FILE IS A VALID CSV FILE
            if($fileType == "csv")
            {
              $goodToUpload = TRUE;
            }
            else
            {
                $error = "Only valid CSV files are allowed";
            }
            if($goodToUpload)
            {
                $target_file = 'stmtToProcess.csv';
                
                //check if file already exists
                if (file_exists($target_dir . $target_file))
                {
                    unlink($target_dir . $target_file);
                }
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], ($target_dir . $target_file))) 
                {
                    $status = "proceedToProcess";
                    $error = "uploadSuccess";
                } 
                else 
                {
                    $error = "There was an error in uploading the file";
                }
            }
            break;
        }
    }   
}


if($status == "")
{
?>
    <div id="corpActUpload">
        <form action="" method="POST" enctype="multipart/form-data" id='fi_main_form1' class="corpActFrm">
            <input id="fileInput" type="file" name="fileToUpload" id="fileToUpload" />
             <input type="hidden" name="formType" value="csvUpload" />
            <input id="uploadBtn" type="submit" value="Upload File" class="corpActBtn" name="submit" disabled />
        </form>
    </div>    
<?php
}    
        
if($error == "uploadSuccess")
{
?>
    <div class="uploadStatus success">✅ File uploaded successfully.</div>
<?php
}
else
{
    if($error != "")
    {
        ?>
            <div class="uploadStatus error">❌ Failed to upload file. Please try again.</div>
        <?php
    }
}
    
if($status == "proceedToProcess")
{
    ?>
    <a href="creditCardReconList.php">STATEMENT SUCCESSFULLY UPLOADED. CLICK HERE TO PROCEED</a>
    <?php
}
?>

<script>
    $(document).ready(function(){
        $('#fileInput').on('change', function(){
            if ($(this).val()) {
                $('#uploadBtn').prop('disabled', false);
            } else {
                $('#uploadBtn').prop('disabled', true);
            }
        });
    });
</script>

<?php
include('footer.php');
?>

