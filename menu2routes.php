<?php

$data        = new stdClass();
$data->error = null;
$itemid      = isset($_GET['Itemid']) && is_numeric($_GET['Itemid']) ? $_GET['Itemid'] : null;
$menutype    = $itemid === null && !empty($_GET['menutype']) ? $_GET['menutype'] : null;
$output      = !empty($_GET['output']) && in_array(strtolower($_GET['output']), ['josn', 'html']) ? strtolower($_GET['output']) : 'json';
$languages   = ['*' => null, 'it' => 'it-IT', 'en' => 'en-GB'];
$filename    = '';
$raw         = empty($_GET['raw']) || $_GET['raw'] === '0' || $_GET['raw'] === 'false' ? true : false;
$target      = empty($_GET['target']) || $_GET['target'] === 'ionic' || $_GET['raw'] === '' ? 'ionic' : 'html';
include 'json.helper.php';
if ($itemid !== null || $menutype !== null)
{
	$ignore         = isset($_GET['ignore']) ? $_GET['ignore'] : null;
	$data->basepath = isset($_GET['path']) ? rtrim(trim($_GET['path'], ' '), '/') : '';
	$data->lang     = isset($_GET['lang']) && in_array(trim($_GET['lang']), $languages) ? trim($_GET['lang']) : '*';
	$data->tree     = null;
	$data->ignore   = empty($ignore) ? null : (is_array($ignore) ? $ignore : explode(',', $ignore));
	$data->ignore   = empty($ignore) ? null : (is_array($ignore) ? $ignore : explode(',', $ignore));
	$filename       = isset($_GET['filename'])
		? trim($_GET['filename'])
		: ($itemid
			? 'menu-Itemid=' . $itemid
			: 'menu-' . $menutype)
		. '.json';

	include 'mysql.helper.php';
	$mysqliHelper = new MysqliHelper();
	try
	{
		$andIgnoreIds = (empty($data->ignore) ? [] : ['  AND (`id` <> ' . implode(') AND (`id` <> ', $data->ignore) . ')']);
		$itemids      = [];
		if ($itemid)
		{
			$sql = array_merge(
				[
					'SELECT `id` as Itemid',
					'  FROM `#__menu`',
					'WHERE (`id`= ' . $itemid . ')',
					'  AND (`published` = 1)'
				],
				$andIgnoreIds,
				($data->lang !== '*'
					? ['  AND (`language` = "*" OR `language` = "' . $mysqliHelper->escape($data->lang) . '")']
					: []));
		}
		else
		{
			$sql = array_merge(
				[
					'SELECT `id` as Itemid',
					'  FROM `#__menu`',
					'WHERE (`#__menu`.`menutype`= "' . $mysqliHelper->escape($menutype) . '")',
					'  AND (`published` = 1)'
				],
				$andIgnoreIds,
				($data->lang === '*'
					? []
					: ['  AND (`language` = "*" OR `language` = "' . $mysqliHelper->escape($data->lang) . '")']));
		}
		$result = $mysqliHelper->query($sql);
		while ($result
			&& ($row = $result->fetch_assoc())
			&& $row['Itemid'])
		{
			$itemids[] = $row['Itemid'];
		}
		if (empty($itemids))
		{
			throw new ErrorException('NO MENUITEM FOUND');
		}
		$component_ids = ['0'];
		$sql           = [
			'SELECT extension_id as component_id',
			'  FROM `#__extensions`',
			'WHERE (`type` = "component")',
			'  AND (`client_id` = 1)',
			'  AND (`enabled` = 1)',
			'  AND (`element` = "com_content" OR `element` = "com_sfsit_template")'];
		$result        = $mysqliHelper->query($sql);
		if ($result instanceof mysqli_result
			&& $result->num_rows > 0)
		{
			// output data of each row
			while ($assoc = $result->fetch_assoc())
			{
				$component_ids[] = $assoc['component_id'];
			}
		}
		$nItems  = 0;
		$aliases = [];
		while ($nItems !== count($itemids))
		{
			$nItems = count($itemids);
			$sql    = array_merge([
				'SELECT `id` as Itemid, `type`, `params`',
				'  FROM `#__menu`',
				'WHERE (`id` = ' . implode(' OR `id` = ', $itemids),
				'       OR `parent_id` = ' . implode(' OR `parent_id` = ', $itemids) . ')',
				'  AND (`published` = 1)',
				'  AND (`component_id` = ' . implode(' OR `component_id` = ', $component_ids) . ')'
			], $andIgnoreIds);
			$result = $mysqliHelper->query($sql);
			if ($result instanceof mysqli_result
				&& $result->num_rows > 0)
			{
				// output data of each row
				while ($row = $result->fetch_object())
				{
					if ($row->type === 'alias')
					{
						$params                = json_decode($row->params);
						$ItemidAlias           = $params->aliasoptions;
						$aliases[$row->Itemid] = $ItemidAlias;
						if (!in_array($ItemidAlias, $itemids)
							&& !empty($data->ignore)
							&& !in_array($row->Itemid, $data->ignore)
							&& !in_array($ItemidAlias, $data->ignore))
						{
							$itemids[] = $row->Itemid;
							$itemids[] = $ItemidAlias;
						}
					}
					elseif (!in_array($row->Itemid, $itemids) && !empty($data->ignore) && !in_array($row->Itemid, $data->ignore))
					{
						$itemids[] = $row->Itemid;
					}
				}
			}
			else
			{
				throw new RuntimeException('ERROR ON LOADING MENU ITEM');
			}
		}
		$menuItems = [];
		$sql       = [
			'SELECT `id`,`parent_id`,`path`,`title`,`alias`,`link`',
			'  FROM `#__menu`',
			'WHERE (`id` = ' . implode(' OR `id` = ', $itemids) . ')'
		];
		if (($result = $mysqliHelper->query($sql))
			&& $result instanceof mysqli_result
			&& $result->num_rows > 0)
		{
			// output data of each row
			while ($menuItem = $result->fetch_object())
			{
				$menuItems[$menuItem->id] = $menuItem;
			}
			if (count($menuItems))
			{
				uasort($menuItems, function ($a, $b) {
					return strcmp($a->path, $b->path);
				});
			}
		}
		// $data->log  = [];
		function tree($rootId, $path, $menuItems, $data, $aliases, $breadcrumbs = []): array
		{
			$nodeItem = (object) [
				'path'      => $menuItems[$rootId]->path . '.html',
				'pathMatch' => 'full',
				'component' => 'JoomlaMenuPage',
				'data'      => (object) [
					'title'       => $menuItems[$rootId]->title,
					'alias'       => $menuItems[$rootId]->alias,
					'itemid'      => $rootId,
					'breadcrumbs' => $breadcrumbs
				]
			];
			if ($menuItems[$rootId]->parent_id !== "1")
			{
				$parentPath    = $menuItems[$menuItems[$rootId]->parent_id]->path;
				$parentPathLen = strlen($menuItems[$menuItems[$rootId]->parent_id]->path);
				if (strlen($menuItems[$rootId]->path) > $parentPathLen
					&& substr($menuItems[$rootId]->path . '/', 0, $parentPathLen + 1) === $parentPath . '/')
				{
					$nodeItem->path = substr($menuItems[$rootId]->path, $parentPathLen + 1) . '.html';
				}
				$nodeItem->data->subtitle = $menuItems[$menuItems[$rootId]->parent_id]->title;
			}
			if (array_key_exists($rootId, $aliases) && $menuItems[$rootId]->link === 'index.php?Itemid=')
			{
				$link                  = $menuItems[$aliases[$rootId]]->link;
				$nodeItem->path        = $menuItems[$aliases[$rootId]]->alias . '.html';
				$nodeItem->data->alias = $menuItems[$aliases[$rootId]]->alias;
			}
			else
			{
				$link = $menuItems[$rootId]->link;
			}
			$linkSplitted = [];
			if (substr($link, 0, 10) === 'index.php?')
			{
				parse_str(substr($link, 10), $linkSplitted);
			}
			if (array_key_exists('option', $linkSplitted)
				&& ($linkSplitted['option'] === 'com_sfsit_template' || $linkSplitted['option'] === 'com_content'))
			{
				if ($linkSplitted['view'] === 'category' || $linkSplitted['view'] === 'article')
				{
					$nodeItem->data->view = 'category';
					$nodeItem->data->id   = $linkSplitted['id'];
				}
				if ($linkSplitted['view'] === 'article')
				{
					$nodeItem->data->view = 'article';
					$nodeItem->data->id   = $linkSplitted['id'];
					$nodeItem->component  = 'JoomlaContentPage';
				}
				elseif ($linkSplitted['view'] === 'category')
				{
					$nodeItem->component = 'JoomlaCategoryPage';
				}
				else
				{
					$nodeItem->data->link = $linkSplitted;
				}
			}
			else
			{
				$nodeItem->data->link = $linkSplitted;
			}
			$nodeChildren = (object) [
				'path'     => substr($nodeItem->path, 0, -5),
				'children' => [
				]];
			$nodeAppend   = [
				(object) [
					'path'      => $nodeChildren->path . '/:id',
					'component' => 'JoomlaContentPage',
					'data'      => (object) [
						'itemid'      => $nodeItem->data->itemid,
						'subtitle'    => $nodeItem->data->title,
						'breadcrumbs' => array_merge(
							$breadcrumbs,
							[(object) ['alias' => $nodeItem->data->alias, 'title' => $nodeItem->data->title]]
						),
						'backTo'      => (empty($path) ? '' : rtrim($path, '/') . '/') . $nodeChildren->path . '.html'
					]],
				(object) [
					'path'       => $nodeChildren->path,
					'redirectTo' => (empty($path) ? '' : rtrim($path, '/') . '/') . $nodeChildren->path . '.html'
				]
			];
			// $data->log[] = 'check for parent_id === ' . $rootId;
			foreach ($menuItems as $n => $menuItem)
			{
				// $data->log[] = 'check (n:' . $n . ') id: => "' . $menuItem->id . '" for parent_id === ' . $rootId;
				// $data->log[] = ' ==> parent_id === ' . $menuItem->parent_id;
				if ($menuItem->parent_id === $rootId)
				{
					$treeChildren = tree(
						(array_key_exists($menuItem->id, $aliases)
							? $aliases[$menuItem->id]
							: $menuItem->id),
						$path . '/' . $nodeItem->data->alias,
						$menuItems,
						$data,
						$aliases,
						array_merge($breadcrumbs, [(object) ['alias' => $nodeItem->data->alias, 'title' => $nodeItem->data->title]]));
					if (count($treeChildren))
					{
						$nodeChildren->children = array_merge(
							$nodeChildren->children,
							$treeChildren
						);
					}
				}
			}
			//	if ($nodeItem->component === 'JoomlaCategoryPage')
			//	{
			//		// DO SOMETHING TO CATEGORIES
			//	}
			return array_merge([$nodeItem], (count($nodeChildren->children) ? [$nodeChildren] : []), $nodeAppend);
		}

		$data->tree = [];
		if ($itemid)
		{
			$data->tree = tree($itemid, $data->basepath, $menuItems, $data, $aliases, [(object) ['alias' => $data->basepath, 'title' => 'home']]);
		}
		else
		{
			$data->tree = [(object) ['id' => '1', 'path' => '', 'children' => []]];
			foreach ($menuItems as $n => $menuItem)
			{
				if ($menuItem->parent_id === '1')
				{
					$data->tree[0]->children = array_merge(
						$data->tree[0]->children,
						tree($menuItem->id,
							$data->basepath,
							$menuItems,
							$data,
							$aliases,
							[(object) ['alias' => $data->basepath, 'title' => 'home']]));
				}
			}
			$data->tree = $data->tree[0]->children;
		}
		$data->menuItems = $menuItems;
	}
	catch (exception $e)
	{
		$message     = $e->getMessage();
		$data->error = !empty($message) ? $message : $mysqliHelper->error();
	}
}
else
{
	$data->error = 'NO ITEMID SET IN QUERY';
}
if ($data->error === null)
{
	unset($data->error);
}
if ($output === 'json')
{
	$jsonHelper = new jsonHelper($data, $filename);
	$jsonHelper->outputJsObj();
}
else // if ($target === 'html')
{
	if (!empty($data->error))
	{
		echo '<h2>' . $data->error . '</h2>';
		exit();
	}
	if (!$raw)
	{
		header('Content-Type: text/plain; charset=utf-8');
	}
	echo '<ul class="nav navbar-nav nav-stacked"'
		. ($data->lang === '*' ? '' : ' *ngIf="langService.lang === \'' . array_search($data->lang, $languages) . '\'"') . '>';

	function htmlUlLi($nodes, $path, $indent = "\n  ", $target): string
	{

		$html = '';
		for ($n = 0; $n < count($nodes); $n++)
		{
			$node = $nodes[$n];
			if (!is_object($node)
				|| !property_exists($node, 'path')
				|| ($node->path === ':id'))
			{
				if (is_array($node))
				{
					$html .= htmlUlLi($node, $path, $indent . '  ', $target);
				}
				continue;
			}
			$havePage         = (property_exists($node, 'data') && property_exists($node->data, 'title'));
			$haveChildren     = (property_exists($nodes[$n], 'children')
				&& is_array($nodes[$n]->children)
				&& count($nodes[$n]->children) > 0);
			$nextHaveChildren = !$haveChildren && (count($nodes) > $n + 1
					&& property_exists($nodes[$n + 1], 'path')
					&& !empty($nodes[$n + 1]->path)
					&& property_exists($nodes[$n + 1], 'children')
					&& is_array($nodes[$n + 1]->children)
					&& count($nodes[$n + 1]->children) > 0);
			if ($havePage || $haveChildren || $nextHaveChildren)
			{
				$html .=
					$indent . '<li>'
					. ($havePage
						?
						$indent . '  <a ' .
						($target === 'ionic'
							? '[routerLink]="\'' . rtrim($path, '/ ') . '/' . $node->path . '\'" routerDirection="root"'
							: 'href="' . rtrim($path, '/ ') . '/' . $node->path . '"')
						. '>'
						. $indent . '    ' . $node->data->title
						. $indent . '  </a>'
						: '')
					. ($haveChildren
					&& ($childrenHtmlUlLi = htmlUlLi($nodes[$n]->children, rtrim($path, '/ ') . '/' . $nodes[$n]->path, $indent . '  ', $target))
					&& !empty($childrenHtmlUlLi)
						? $indent . '  <ul class="nav navbar-nav nav-stacked nav-child">'
						. $childrenHtmlUlLi
						. $indent . '  </ul>'
						: '')
					. ($nextHaveChildren
					&& ($nextHtmlUlLi = htmlUlLi($nodes[$n + 1]->children, rtrim($path, '/ ') . '/' . $nodes[$n + 1]->path, $indent . '    ', $target))
					&& !empty($nextHtmlUlLi)
						? $indent . '  <ul class="nav navbar-nav nav-stacked nav-child">'
						. $nextHtmlUlLi
						. $indent . '  </ul>'
						: '')
					. $indent . '</li>';
				if ($nextHaveChildren)
				{
					$n++;
				}
			}

		}

		return $html;
	}

	echo htmlUlLi($data->tree, $data->basepath, "\n  ", $target);
	echo "\n</ul>";
}
