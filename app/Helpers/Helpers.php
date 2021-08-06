<?php

namespace App\Helpers;

class Helpers
{
    public static function clearLink($shopUrl, $url, $host = false)
    {
        $nl = "";
        foreach (explode("/", $shopUrl) as $key => $val) {
            if (sizeof(explode("/", $shopUrl)) - 1 > $key) {
                $old = explode("/", $url);
                foreach ($old as $kk => $vv) {
                    if ($vv == $val) {
                        $val = "";
                    }
                }
                $nl .= $val . "/";
            }
        }
        if ($host) {
            if (sizeof(explode("/", $nl)) > 2) {
                $old = explode("/", $nl);
                $nl = "";
                foreach ($old as $kk => $vv) {
                    if ($kk <= 2) {
                        $nl .= $vv . "/";
                    }
                }
            }
            $nl .= "/";
        }

        if ($nl != "") {
            $nl = str_replace("@", "://", str_replace("//", "", str_replace("://", "@", $nl)));
            $shopUrl = $nl . $url;
        }
        return $shopUrl;
    }

    public static function clean($string)
    {
        $string = str_replace("'", "", $string);
        $string = preg_replace('/[^A-Za-z0-9 -\/]/', '', $string); // Removes special chars.

        return $string;
    }

    public static function csv_to_array($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return FALSE;
        }
        $header = NULL;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if ($header == NULL) {
                    $header = array();
                    foreach ($row as $val) {
                        $header_raw[] = $val;
                        $hcounts = array_count_values($header_raw);
                        $header[] = $hcounts[$val] > 1 ? $val . $hcounts[$val] : $val;
                    }

                } else {
                    if (sizeof($header) == sizeof($row)) {
                        $data[] = array_combine($header, $row);
                    }
                }
            }
            fclose($handle);
        }
        return $data;
    }

    public static function temporaryFile($name, $content)
    {
        $file = DIRECTORY_SEPARATOR .
            trim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            ltrim($name, DIRECTORY_SEPARATOR);

        file_put_contents($file, $content);
        register_shutdown_function(function () use ($file) {
            unlink($file);
        });
        return $file;
    }
}
