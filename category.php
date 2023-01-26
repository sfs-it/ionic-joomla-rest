<?php

$data        = new stdClass();
$data->error = null;

$catId    = isset($_GET['catid']) ? trim($_GET['catid']) : null;
$itemid   = isset($_GET['Itemid']) && is_numeric($_GET['Itemid']) ? $_GET['Itemid'] : null;
$filename = '';
include 'json.helper.php';
if (!empty($catId) || !empty($itemid))
{
	include 'joomla.helper.php';
	$catIds      = [$catId];
	$dataPage    = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
	$lang        = isset($_GET['lang']) && in_array(trim($_GET['lang']), ['it-IT', 'en-GB']) ? trim($_GET['lang']) : 'it-IT';
	$depth       = isset($_GET['depth']) ? trim($_GET['depth']) : '0';
	$filename    = isset($_GET['filename']) ? trim($_GET['filename']) : 'category=' . $catIds . '-' . $lang . '-page=' . $dataPage . '.json';
	$depth       = is_numeric($depth) ? (int) $depth : 0;
	$data->error = null;
	$data->lang  = $lang;
	$data->catid = $catId;

	try
	{
		$joomlaHelper = new JoomlaHelper();
		$joomlaHelper->setLang($data->lang);

		$params = [];
		$getParamsSql
		        = [
			'menu'         =>
				$itemid ?
					'SELECT `params`,`type`'
					. ' FROM `#__menu`'
					. '  WHERE `id` = ' . $itemid
					: '',
			'menu_sfsit'   =>
				$itemid ?
					'SELECT `params`'
					. ' FROM `#__menu`'
					. '  WHERE `link` = "index.php?option=com_sfsit_template&view=category&layout=blog&id=' . $catId . '"'
					: '',
			'menu_content' =>
				'SELECT `params`'
				. ' FROM `#__menu`'
				. '  WHERE `link` = "index.php?option=com_content&view=category&layout=blog&id=' . $catId . '"',
			'defaults'     =>
				'SELECT `params`'
				. ' FROM `#__extensions`'
				. '  WHERE `name` = "com_content"'
		];
		$itemid = $joomlaHelper->getMenuItem($itemid, $getParamsSql, $params);

		if ($dataPage === 1)
		{
			$data->category = $joomlaHelper->getCategory($catId,$params);
		}


		$mysqliHelper = new MysqliHelper();
		if ($depth > 0)
		{
			$n           = $depth;
			$lastMatches = 0;
			while (($depth === -1 || ($depth > -1 && $n > 0)) && count($catIds) > $lastMatches)
			{
				$sql    = 'SELECT DISTINCT(`id`) FROM `#__categories`'
					. 'WHERE ('
					. '  ( (`id` = ' . implode(' OR `id` = ', $catIds) . ')'
					. '    OR (`parent_id` = ' . implode(' OR `parent_id` = ', $catIds) . ') )'
					. ' AND (`published` = 1)'
					. ' AND (`access` = 1)'
					. ' AND (`language` = "*" OR `language` = "' . $mysqliHelper->escape($lang) . '")'
					. ')';
				$result = $mysqliHelper->query($sql);
				if ($result && $result->num_rows > 0)
				{
					$lastMatches = count($catIds);
					$catIds      = [];
					while ($row = $result->fetch_row())
					{
						$catIds[] = $row[0];
					}
				}
				else
				{
					break;
				}
				$n--;
			}
		}


		$data->nItems = 0;
		$data->limit  = isset($_GET['limit']) && is_numeric($_GET['limit']) ? $_GET['limit'] : 10;
		$data->offset = ($dataPage - 1) * $data->limit;
		$data->items  = [];
		$where        = 'WHERE ('
			. '(`catid` = ' . $catId
			. (count($catIds) === 0
				? ''
				: ' OR `catid` = ' . implode(' OR `catid` = ', $catIds))
			. ')'
			. ' AND (`state` = 1)'
			. ' AND (`access` = 1)'
			. ' AND (`language` = "*" OR `language` = "' . $mysqliHelper->escape($lang) . '")'
			. ' AND (`publish_up` < NOW() OR `publish_up` = "0000-00-00 00:00:00")'
			. ' AND (`publish_down` > NOW() OR `publish_down` = "0000-00-00 00:00:00")'
			. ')';

		$sql    = 'SELECT COUNT(`id`) as nItems'
			. '  FROM `#__content`'
			. '  ' . $where;
		$result = $mysqliHelper->query($sql);
		if ($result instanceof mysqli_result)
		{
			$data->nItems = (int) $result->fetch_assoc()['nItems'];
			$sql          =
				'SELECT `id`, `alias`, `title`, `catid`, `introtext`, `language` as `lang`, `created` , `modified`, `images`, `urls`'
				. ' FROM `#__content`'
				. '  ' . $where
				. '  ORDER BY `#__content`.`created` DESC'
				. '  LIMIT ' . $data->offset . ',' . $data->limit;
			$result       = $mysqliHelper->query($sql);

			if ($result && $result->num_rows > 0)
			{
				$n = 0;
				// output data of each row
				while ($item = $result->fetch_assoc())
				{
					foreach (['images', 'urls', 'attribs'] as $k)
					{
						if (isset($item[$k]) && !empty($item[$k]))
						{
							$item[$k] = json_decode($item[$k]);
						}
					}
					$data->items[] = array_merge(['nItem' => ++$n + $data->offset], $item);
				}
			}
		}
		else
		{
			$data->error = $mysqliHelper->error();
		}
	}
	catch (exception $e)
	{
		$data->error = $mysqliHelper->error();
	}
}
else
{
	$data->error = 'NO CATEGORY SET';
}
if (empty($data->error))
{
	unset($data->error);
}
$jsonHelper = new jsonHelper($data, isset($data->error) ? '' : $filename);
$jsonHelper->output();
