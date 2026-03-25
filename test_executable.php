<?php

$path = 'C:\poppler\Library\bin\pdftotext';
echo 'is_executable WITHOUT exe: '.(is_executable($path) ? 'true' : 'false')."\n";
$path2 = 'C:\poppler\Library\bin\pdftotext.exe';
echo 'is_executable WITH exe: '.(is_executable($path2) ? 'true' : 'false')."\n";
