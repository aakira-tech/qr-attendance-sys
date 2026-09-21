<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="student_import_template.csv"');

$out = fopen('php://output', 'w');

fputcsv($out, ['student_id', 'full_name', 'grade_level']);

fputcsv($out, ['123456789012', 'Dela Cruz, Juan', 'Grade 4']);
fputcsv($out, ['123456789013', 'Santos, Maria', 'Grade 4']);
fputcsv($out, ['123456789014', 'Reyes, Pedro', 'Grade 4']);

fclose($out);
exit;
?>