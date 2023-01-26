<?php
if (!class_exists('JConfig'))
{
	include_once "configuration.php";
}

if (!class_exists('MysqliHelper'))
{
	define('__MYSQL_HELPER__', true);

	class MysqliHelper
	{
		private $config;
		private $mysqli;
		private $replaces;
		private $error_msg;

		function __construct()
		{
			$this->config = new JConfig();
			$this->mysqli = new mysqli($this->config->host, $this->config->user, $this->config->password, $this->config->db);
			// Check connection
			if ($this->mysqli->connect_error)
			{
				die("Connection failed: " . $this->mysqli->connect_error);
			}
			$error_msg      = '';
			$this->replaces = ['#__' => $this->config->dbprefix];
			$this->mysqli->set_charset("utf8");
		}

		function __destruct()
		{
			$this->mysqli->close();
		}

		function query($sql)
		{
			if (is_array($sql))
			{
				$sql = implode("\n", $sql);
			}
			foreach ($this->replaces as $replace => $with)
			{
				$sql = str_replace($replace, $with, $sql);
			}
			try
			{
				return $this->mysqli->query($sql);
			}
			catch (exception $e)
			{

				$this->error_msg = $this->mysqli->error() . "\nSQL:" . is_array($sql) ? implode("\n", $sql) : $sql;

				return null;
			}
		}

		function error(): ?string
		{
			return $this->error_msg;
		}

		function escape($str): string
		{
			return $this->mysqli->real_escape_string($str);
		}
	}
}
