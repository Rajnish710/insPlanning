<?php 
include('../includes/dbcon.php');
$inqNo = $_POST['inqNo'];
$version = $_POST['version'];
?>
<div class="d-flex">
    <span class="input-group-text" style = " border:1px solid #25022c; color: #25022c">Inq NO</span>
    <input type="text" class="form-control inqNo " name="inqNo" style="width: fit-content;" value="<?php echo $inqNo ?>">
    <span class="input-group-text ms-2" style = " border:1px solid #25022c; color: #25022c">Version</span>
    <input type="text" class="form-control version" name="version" style="width: fit-content;" value="<?php echo $version ?>">
</div>
        <table class="table table-bordered mt-4">
            <thead align="center" style="background-color:#c3adcd4f">
                <tr>
                    <th width="15%">Sr No</th>
                    <th width="40%">Comment</th>
                    <th width="40%">Response</th>
                    <th width="5%"><a class="btn btn-primary btn-sm" id="addRowBtn"><b><span>+</span></b></a></th>
                    
                </tr>
            </thead>
            <tbody id="itemTableBody">
                <?php
                $sql = "SELECT * FROM CRS WHERE inqNo='$inqNo' AND version='$version'";
                $run = sqlsrv_query($con, $sql);
                while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){ ?>
                    <tr>
                    
                        <td>
                            <input type="number" class="form-control srnoU" style="text-align:center" name="srno[]" value = "<?php echo $row['srno'] ?>">
                            <input type="hidden" class="form-control" style="text-align:center" name="id[]" value = "<?php echo $row['id'] ?>">
                            <input type="hidden" class="form-control ok" name="ok[]" >
                        </td>
                        <td>
                            <input type="text" class="form-control commentU" name="comment[]" value = "<?php echo $row['comment'] ?>">
                        </td>
                        <td>
                            <input type="text" class="form-control responseU" name="response[]" value = "<?php echo $row['response'] ?>">
                            
                        </td>
                        <td></td>
                    </tr>
                <?php }
                ?>
                <tr>
                    <td>
                        <input type="number" class="form-control srno" style="text-align:center" id="srno" name="srno[]">
                        <input type="hidden" class="form-control" style="text-align:center" name="id[]">
                        <input type="hidden" class="form-control ok" name="ok[]">
                    </td>
                    <td>
                        <input type="text" class="form-control comment" id="comment" name="comment[]">
                    </td>
                    <td>
                        <input type="text" class="form-control response" id="response" name="response[]">
                    </td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <script>
            $(document).on('change','.srnoU,.commentU,.responseU',function(){
                $(this).closest('tr').find('.ok').val('1');
            })
        </script>