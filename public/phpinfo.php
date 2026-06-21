<?php
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "Loaded extensions: " . implode(', ', get_loaded_extensions());
