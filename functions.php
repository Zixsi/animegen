<?php

define('ROOTDIR', __DIR__);
define('DATADIR', ROOTDIR . '/data/');

function writeFile($path, $data, $mode = 'wb')
{
	if (!$fp = @fopen($path, $mode))
	{
		return FALSE;
	}

	flock($fp, LOCK_EX);

	for($result = $written = 0, $length = strlen($data); $written < $length; $written += $result)
	{
		if (($result = fwrite($fp, substr($data, $written))) === FALSE)
		{
			break;
		}
	}

	flock($fp, LOCK_UN);
	fclose($fp);
	return is_int($result);
}

function deleteFile($path, $del_dir = false, $htdocs = false, $_level = 0)
{
	$path = rtrim($path, '/\\');
	
	if ( ! $current_dir = @opendir($path))
	{
		return false;
	}

	while (false !== ($filename = @readdir($current_dir)))
	{
		if ($filename !== '.' && $filename !== '..')
		{
			$filepath = $path.DIRECTORY_SEPARATOR.$filename;
			if (is_dir($filepath) && $filename[0] !== '.' && ! is_link($filepath))
			{
				deleteFile($filepath, $del_dir, $htdocs, $_level + 1);
			}
			elseif ($htdocs !== true OR ! preg_match('/^(\.htaccess|index\.(html|htm|php)|web\.config)$/i', $filename))
			{
				@unlink($filepath);
			}
		}
	}

	closedir($current_dir);
	return ($del_dir === true && $_level > 0)?@rmdir($path):true;
}

function isReallyWritable($file)
{
	if(DIRECTORY_SEPARATOR === '/')
	{
		return is_writable($file);
	}

	if(is_dir($file))
	{
		$file = rtrim($file, '/').'/'.md5(mt_rand());
		if (($fp = @fopen($file, 'ab')) === false)
		{
			return false;
		}
		fclose($fp);
		@chmod($file, 0777);
		@unlink($file);
		return true;
	}
	elseif(!is_file($file) OR ($fp = @fopen($file, 'ab')) === false)
	{
		return false;
	}
	fclose($fp);

	return true;
}

function forceDownload($filename = '', $data = '', $set_mime = false)
{
	if ($filename === '' OR $data === '')
	{
		return;
	}
	elseif ($data === NULL)
	{
		// Is $filename an array as ['local source path' => 'destination filename']?
		if (is_array($filename))
		{
			if (count($filename) !== 1)
			{
				return;
			}
			reset($filename);
			$filepath = key($filename);
			$filename = current($filename);
			if (is_int($filepath))
			{
				return;
			}
		}
		else
		{
			$filepath = $filename;
			$filename = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $filename));
			$filename = end($filename);
		}
		if ( ! @is_file($filepath) OR ($filesize = @filesize($filepath)) === false)
		{
			return;
		}
	}
	else
	{
		$filesize = strlen($data);
	}
	// Set the default MIME type to send
	$mime = 'application/octet-stream';
	$x = explode('.', $filename);
	$extension = end($x);
	if ($set_mime === true)
	{
		if (count($x) === 1 OR $extension === '')
		{
			/* If we're going to detect the MIME type,
			 * we'll need a file extension.
			 */
			return;
		}
		// Load the mime types
		$mimes =& get_mimes();
		// Only change the default MIME if we can find one
		if (isset($mimes[$extension]))
		{
			$mime = is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
		}
	}
	/* It was reported that browsers on Android 2.1 (and possibly older as well)
	 * need to have the filename extension upper-cased in order to be able to
	 * download it.
	 *
	 * Reference: http://digiblog.de/2011/04/19/android-and-the-download-file-headers/
	 */
	if (count($x) !== 1 && isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Android\s(1|2\.[01])/', $_SERVER['HTTP_USER_AGENT']))
	{
		$x[count($x) - 1] = strtoupper($extension);
		$filename = implode('.', $x);
	}
	// Clean output buffer
	if (ob_get_level() !== 0 && @ob_end_clean() === false)
	{
		@ob_clean();
	}
	// Generate the server headers
	header('Content-Type: '.$mime);
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Expires: 0');
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: '.$filesize);
	header('Cache-Control: private, no-transform, no-store, must-revalidate');

	// If we have raw data - just dump it
	if ($data !== NULL)
	{
		exit($data);
	}
	// Flush the file
	if (@readfile($filepath) === false)
	{
		return;
	}
	exit;
}

function responce($status = false, $data = [])
{
	echo json_encode(['result' => $status, 'data' => $data]); die();
}

function person_tpl()
{
	return [
		'name' => '',
		'params' => [
			'character' => [],
			'interaction' => []
		]
	];
}

function person_add($data)
{
	if(!empty($data['name']))
	{
		$hash = md5($data['name']);
		if(file_exists(DATADIR.$hash) == false)
		{
			$tpl = person_tpl();
			$tpl['name'] = $data['name'];

			if(writeFile(DATADIR.$hash, json_encode($tpl)))
			{
				chmod(DATADIR.$hash, '0777');
				return true;
			}
		}
	}

	return false;
}

function person_save($hash, $data)
{
	if(file_exists(DATADIR.$hash))
	{
		return writeFile(DATADIR.$hash, json_encode($data));
	}

	return false;
}

function person_del($hash)
{
	if(file_exists(DATADIR.$hash))
	{
		return unlink(DATADIR.$hash);
	}

	return false;
}

function person_list()
{
	$result = [];

	if($files = scandir(DATADIR))
	{
		foreach($files as $file)
		{
			if($file == '.' OR $file == '..')
			{
				continue;
			}

			if($item = person_get($file))
			{
				$item['hash'] = $file;
				$result[] = $item;
			}
		}
	}

	return $result;
}

function person_get($hash)
{
	if(file_exists(DATADIR.$hash))
	{
		$data = file_get_contents(DATADIR.$hash);
		return json_decode($data, true);
	}

	return false;
}

function ch_add($hash, $name)
{
	if(!empty($name))
	{
		if($item = person_get($hash))
		{
			if(in_array($name, $item['params']['character']))
			{
				return true;
			}
			else
			{
				$item['params']['character'][] = mb_strtolower($name);
				return person_save($hash, $item);
			}
		}
	}

	return false;
}

function interaction_add($hash, $name)
{
	if(!empty($name))
	{
		if($item = person_get($hash))
		{
			if(in_array($name, $item['params']['interaction']))
			{
				return true;
			}
			else
			{
				$item['params']['interaction'][] = mb_strtolower($name);
				return person_save($hash, $item);
			}
		}
	}

	return false;
}

function gen($hash)
{
	if($item = person_get($hash))
	{
		if(count($item['params']['character']) && count($item['params']['interaction']))
		{
			$result = [];

			foreach($item['params']['character'] as $val)
			{
				foreach($item['params']['interaction'] as $v)
				{
					$result[] = [$val, $item['name'], $v];
				}
			}

			shuffle($result);
			return $result;
		}
	}

	return false;
}

function current_gen($hash)
{
	if($item = person_get($hash))
	{
		if(count($item['params']['character']) && count($item['params']['interaction']))
		{
			$result = [];

			$result[] = $item['params']['character'][array_rand($item['params']['character'])];
			$result[] = $item['name'];
			$result[] = $item['params']['interaction'][array_rand($item['params']['interaction'])];

			return implode(" ", $result);
		}
	}

	return false;
}

function make_download($data = [])
{
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=gen.tsv');
	$output = fopen('php://output', 'w');
	foreach($data as $val)
	{
		fputcsv($output, $val, "\t");
	}
	die();
}

function del_param($hash, $name, $type)
{
	if($item = person_get($hash))
	{
		$key = null;

		switch ($type)
		{
			case 'ch':
				$key = 'character';
			break;

			case 'interaction':
				$key = 'interaction';
			break;
			
			default:
				// empty
			break;
		}

		if($key)
		{
			foreach($item['params'][$key] as $k => $val)
			{
				if($val == $name)
				{
					unset($item['params'][$key][$k]);
				}
			}

			$item['params'][$key] = array_values($item['params'][$key]);

			if(person_save($hash, $item))
			{
				return true;
			}
		}
	}

	return false;
}