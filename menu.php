<?php

$data        = new stdClass();
$data->error = null;
$data->itemid    = isset($_GET['Itemid']) && is_numeric($_GET['Itemid']) ? $_GET['Itemid'] : 0;
$data->lang  = isset($_GET['lang']) && in_array(trim($_GET['lang']), ['it-IT', 'en-GB']) ? trim($_GET['lang']) : 'it-IT';

include 'mysql.helper.php';
include 'json.helper.php';

$mysqliHelper = new MysqliHelper();
$where        = 'WHERE ('
	. '(`itemid`= ' . $mysqliHelper->escape($data->itemid) . ')'
	. ' AND (`state` = 1)'
	. ' AND (`access` = 1)'
	. ' AND (`language` = "*" OR `language` = "' . $mysqliHelper->escape($data->lang) . '")'
	. ' AND (`publish_up` < NOW() OR `publish_up` = "0000-00-00 00:00:00")'
	. ' AND (`publish_down` > NOW() OR `publish_down` = "0000-00-00 00:00:00")'
	. ')';
$sql          = 'SELECT COUNT(`id`) as nItems'
	. '  FROM `#__menu`'
	. '  ' . $where;
$result       = $mysqliHelper->query($sql);
if ($result)
{
	$sql =
		'SELECT `id`, `alias`, `title`, `introtext`,`fulltext`, `language` as `lang`, `created` , `modified`, `images`, `urls`, `attribs`'
		. ' FROM `#__content`'
		. '  ' . $where;
	try
	{
		$result = $mysqliHelper->query($sql);

		if ($result && $result->num_rows > 0)
		{
			if ($item = $result->fetch_assoc())
			{
				foreach (['images', 'urls', 'attribs'] as $k)
				{
					if (isset($item[$k]) && !empty($item[$k]))
					{
						$item[$k] = json_decode($item[$k]);
					}
				}
				foreach ($item as $k => $v)
				{
					$data->$k = $v;
				}
			}
		}
	}
	catch (exception $e)
	{
		$data->error = $mysqliHelper->error() . "\nSQL:" . $sql;
	}
}
else
{
	$data->error = $mysqliHelper->error();
}
if ($data->error === null)
{
	unset($data->error);
}

$jsonHelper = new jsonHelper($data);
$jsonHelper->setFilename('content-id=' . $data->id /* . '-' . $data->alias */ . '.json');
$jsonHelper->output();
