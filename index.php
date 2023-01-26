<?php
$data = [];
for ($i = 0; $i < 10; $i++)
{
	$item   = new stdClass();
	$data[] = $item;
}
header("Content-Type: application/json");
echo json_encode($data);
exit();
