#!/usr/bin/php
<?php

$conn = new mysqli('127.0.0.1', 'testuser', '12345', 'main_help_db');

$deleteSeshQuery = "DELETE * FROM systems WHERE date < DATEADD(day, -1, GETDATE())";

if (mysqli_query($conn, $deleteSeshQuery)) 
{
    echo "Deleted session keys older than a day successfully";
} 
else 
{
    publishLog("Error delting session keys: " . mysqli_error($conn));
}

exit();
?>