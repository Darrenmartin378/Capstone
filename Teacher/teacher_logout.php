<?php
session_start();
session_destroy();
header("Location: /capstone/Teacher/teacher_login.php");
exit;