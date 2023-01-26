<?php

if (!class_exists('jsonHelper'))
{
	define('__JSON_HELPER__', true);

	class jsonHelper
	{
		private $data, $filename, $type;


		function __construct($data, $filename = '')
		{
			$this->data = $data;
			if ($filename === '')
			{
				$this->type = 'inline';
			}
			else
			{
				$this->setFilename($filename);
			}
		}

		function setFilename($filename)
		{
			$this->type     = 'attachment';
			$this->filename = $filename;

			return $filename;
		}

		private function outputSetHeaders(&$json)
		{
			header_remove();
			header('Content-Type: application/json; charset=utf-8');
			if ($json === false)
			{
				// Avoid echo of empty string (which is invalid JSON), and
				// JSONify the error message instead:
				$json = json_encode(["error" => 'jsonError: ' . json_last_error_msg()]);
				if ($json === false)
				{
					// This should not happen, but we go all the way now:
					$json = '{"error":"jsonError:  unknown encoding error"}';
				}
				// Set HTTP response status code to: 500 - Internal Server Error
				http_response_code(500);
			}
			if ($this->type === 'inline')
			{
				header('Content-Disposition: inline;');
			}
			else if ($this->type === 'attachment')
			{
				header('Content-Disposition: attachment; filename="' . (substr($this->filename, -5) === '.json' ? $this->filename : $this->filename . '.json') . '"');
			}
		}

		function output()
		{
			$json = json_encode($this->data, $this->filename === '' ? 0 : JSON_PRETTY_PRINT);
			$this->outputSetHeaders($json);
			echo $json;
			exit();
		}

		function outputJsObj()
		{
			$json = json_encode($this->data, $this->filename === '' ? 0 : JSON_PRETTY_PRINT);
			$this->outputSetHeaders($json);
			echo preg_replace(['/"([^"]+)":/', '/"([^"]*)"(,?)$/m'], ['\1:', '\'\1\'\2'],
				str_replace(
					['\\/', '"JoomlaContentPage"', '"JoomlaCategoryPage"', '"JoomlaMenuPage"'],
					['/', 'JoomlaContentPage', 'JoomlaContentPage', 'JoomlaContentPage'],
					$json));
		}
	}

}
