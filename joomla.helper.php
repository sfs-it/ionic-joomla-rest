<?php
if (!class_exists('MysqliHelper'))
{
	include_once 'mysql.helper.php';
}
if (!class_exists('JoomlaHelper'))
{
	class JoomlaHelper
	{
		private $mysqliHelper;
		private $lang = '*';

		function __construct()
		{
			$this->mysqliHelper = new MysqliHelper();
		}

		function setLang($lang)
		{
			$this->lang = $lang;
		}

		function getLang()
		{
			return $this->lang;
		}

		function getContent($id)
		{
			$where  = 'WHERE ('
				. '(`id`= ' . $id . ')'
				. ' AND (`state` = 1)'
				. ' AND (`access` = 1)'
				. ' AND (`language` = "*"' . ($this->lang === '*' ? '' : 'OR `language` = "' . $this->mysqliHelper->escape($this->lang) . '"') . ')'
				. ' AND (`publish_up` < NOW() OR `publish_up` = "0000-00-00 00:00:00")'
				. ' AND (`publish_down` > NOW() OR `publish_down` = "0000-00-00 00:00:00")'
				. ')';
			$result = $this->mysqliHelper->query(
				'SELECT COUNT(`id`) as nItems'
				. '  FROM `#__content`'
				. '  ' . $where);
			if (!$result)
			{
				throw new RuntimeException('ERROR ON CHECK ARTICLES MATCHING');
			}

			$result = $this->mysqliHelper->query(
				'SELECT `id`, `alias`, `title`, `catid`, `introtext`,`fulltext`, `language` as `lang`, `created` , `modified`, `images`, `urls`, `attribs`'
				. ' FROM `#__content`'
				. '  ' . $where);

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
				}

				return $item;
			}

			return null;
		}

		function getCategory($catId)
		{
			$sql = 'SELECT `alias`, `title`, `description`,`params`, `created_time` AS `created`, `modified_time` AS `modified` FROM `#__categories`'
				. '  WHERE (`id` = ' . $catId . ')'
				. '    AND (`published` = 1)'
				. '    AND (`access` = 1)'
				. '    AND (`language` = "*"' . ($this->lang === '*' ? '' : ' OR `language` = "' . $this->mysqliHelper->escape($this->lang) . '"') . ')';
			if (($result = $this->mysqliHelper->query($sql))
				&& $result instanceof mysqli_result
				&& $result->num_rows > 0
				&& $category = $result->fetch_assoc())
			{
				$category['params'] = json_decode($category['params']);
			}
			else
			{
				throw new RuntimeException('ERROR ON CATEGORY "' . $catId . '" :: ' . $this->mysqliHelper->error());
			}
			$sql =
				'SELECT `id`, `alias`, `title`, `alias`, `description` as `lang`, `created_time` AS `created` , `modified_time` AS `modified`, `params`'
				. '  FROM `#__categories`'
				. '  WHERE `parent_id` = ' . $catId
				. '    AND `published` = 1'
				. '    AND `access` = 1'
				. '    AND `extension` = "com_content"'
				. '  ORDER BY `created` DESC';
			$category['subcats']
			     = [];
			if (($result = $this->mysqliHelper->query($sql))
				&& $result instanceof mysqli_result
				&& $result->num_rows > 0)
			{
				// output data of each row
				while ($subcategory = $result->fetch_assoc())
				{
					$subcategory['params'] = json_decode($subcategory['params']);
					$category['subcats'][] = $subcategory;
				}
			}
			else
			{
				throw new RuntimeException('ERROR LOADING SUBCATEGORIES FOR "' . $catId . '" :: ' . $this->mysqliHelper->error());
			}

			return $category;
		}

		private function getComponentParams()
		{
			if (empty($this->componentParams))
			{
				$this->componentParams = [];
				$result                = $this->mysqliHelper->query(
					'SELECT `name`,`params`'
					. ' FROM `#__extensions`'
					. '  WHERE `name` = "com_sfsit_template"'
					. '      OR `name` = "com_content"');
				if ($result instanceof mysqli_result
					&& $result->num_rows > 0)
				{
					while ($row = $result->fetch_row())
					{
						if (!empty($row))
						{
							$this->componentParams[$row[0]] = json_decode($row[1]);
						}
					}
				}
			}

			return $this->componentParams;
		}

		function getMenuItem($itemid, $id)
		{

			$getParamsSql = [
				'menu'         =>
					$itemid ?
						'SELECT `params`,`type`,`link`'
						. ' FROM `#__menu`'
						. '  WHERE `id` = ' . $itemid
						: '',
				'menu_sfsit'   =>
					$id ?
						'SELECT `params`'
						. ' FROM `#__menu`'
						. '  WHERE `link` = "index.php?option=com_sfsit_template&view=article&id=' . $id . '"'
						. '  LIMIT 1'
						: '',
				'menu_content' =>
					$id ?
						'SELECT `params`'
						. ' FROM `#__menu`'
						. '  WHERE `link` = "index.php?option=com_content&view=article&id=' . $id . '"'
						. '  LIMIT 1'
						: ''
			];
			$params       = [];
			$menuLink     = false;
			foreach ($getParamsSql as $key => $sql)
			{
				$result = $sql ? $this->mysqliHelper->query($sql) : false;
				if (!($result instanceof mysqli_result)
					|| ($result->num_rows === 0))
					continue;
				$row = $result->fetch_row();
				if (!$row)
				{
					throw new RuntimeException('ERROR LOADING MENU ITEM');
				}
				$params = json_decode($row[0]);
				if ($params === null)
				{
					throw new RuntimeException('ERROR ON DEFAULTS JSON DECODING "' . $row[0] . '"');
				}
				if ($key === 'menu')
				{
					if ($row[1] === 'alias' && !empty($params->aliasoptions))
					{
						while ($row[1] === 'alias' && !empty($params[$key]->aliasoptions))
						{
							$itemid = $params[$key]->aliasoptions;
							$result = $this->mysqliHelper->query('SELECT `params`,`type`,`link`'
								. ' FROM `#__menu`'
								. '  WHERE `id` = ' . $itemid);
							if ($result && $result->num_rows > 0)
							{
								$row = $result->fetch_row();
								if (!$row)
								{
									throw new RuntimeException('ERROR LOADING MENU ITEM "' . $itemid . '"');
								}
								$params = json_decode($row[0]);
								if ($params === null)
								{
									throw new RuntimeException('ERROR ON DEFAULTS JSON DECODING "' . $row[0] . '"');
								}
								if ($row[1] === 'component')
								{
									break;
								}
							}
							else
							{
								throw new RuntimeException('ERROR ON LOADING MENU ITEM "' . $itemid . '"');
							}
						}
					}
				}
				if ($row && $row[1] === 'component')
				{
					$menuLink = $row[2];
				}

				$this->getComponentParams();
				return [$itemid, $menuLink, $params];
			}


			return $itemid;
		}

		function error(): ?string
		{
			return $this->mysqliHelper->error();
		}
	}
}
