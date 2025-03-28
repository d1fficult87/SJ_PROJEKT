<?php
    if ($_SERVER["REQUEST_METHOD"] == "POST"){
        $name = $_POST['name'];
        $email = $_POST['email'];
        $inquiry = $_POST['inquiry'];
        $message = $_POST['message'];

        echo "<h2>Form Submission</h2>";
        echo "<p>Name: $name</p>";
        echo "<p>Email: $email</p>";
        echo "<p>Inquiry: $inquiry</p>";
        echo "<p>Message: $message</p>";

    }else{
        echo "<h2>Error</h2>";
    }  
?>