<?php
header('Content-Type: text/plain');
echo getenv('DB_NAME') ? 'YES' : 'NO';
