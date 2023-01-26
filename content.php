<?php

$data        = new stdClass();
$data->error = null;

$id       = isset($_GET['id']) && is_numeric($_GET['id']) ? $_GET['id'] : null;
$itemid   = isset($_GET['Itemid']) && is_numeric($_GET['Itemid']) ? $_GET['Itemid'] : null;
$filename = '';
include 'json.helper.php';
if (!empty($id) || !empty($itemid))
{
	include 'joomla.helper.php';
	$data->id   = $id;
	$filename   = isset($_GET['filename']) ? trim($_GET['filename']) : 'content-id=' . $id . ($itemid ? '-Itemid=' . $itemid : '') . '.json';
	$data->lang = isset($_GET['lang']) && in_array(trim($_GET['lang']), ['it-IT', 'en-GB']) ? trim($_GET['lang']) : 'it-IT';


	try
	{

		$joomlaHelper = new JoomlaHelper();
		$joomlaHelper->setLang($data->lang);

		$params = [];
		[$itemid, $menuLink, $params] = $joomlaHelper->getMenuItem($itemid, $id);

		$content = $joomlaHelper->getContent($id);

		// MERGE CONTENT PARAMS
		if ($content)
		{
			if (!empty($itemid))
			{
				$data->itemid = $itemid;
			}
			if (!empty($menuLink)
				&& substr($menuLink, 0, 31) === 'index.php?option=com_sfsit_template&view=article')
			{
			}
			// merge results
			foreach ($params as $k => $v)
				// foreach ($item['attribs'] as $k => $v)
			{
				// Grab all default settings and use if not present on article content
				if (/* $v !== '' && */
				(!isset($content['attribs']->$k) || $content['attribs']->$k === ''))
					// Grab default settings only if article content setting present and empty
					// if (isset($item['attribs']->$k) && $item['attribs']->$k === '')
				{
					$content['attribs']->$k = $v;
				}
			}
			foreach ($content as $k => $v)
			{
				$data->$k = $v;
			}
		}
		else
		{
			$data->error = 'DATA NOT FOUND';
		}
	}
	catch (exception $e)
	{
		if (empty($data->error))
		{
			$data->error = isset($joomlaHelper) ? $joomlaHelper->error() : 'failed to init JoomlaHelper';
		}
	}
	if ($data->error === null)
	{
		unset($data->error);
	}
}
else
{
	$data->error('Id of content or its menuitem id (Itemid) must be set');
}
$jsonHelper = new jsonHelper($data, $filename);
$jsonHelper->output();
