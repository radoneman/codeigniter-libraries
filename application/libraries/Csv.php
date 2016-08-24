<?php
if (! defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Export data as CSV with columns headers
 * Allows the export of large data sets by calling the methods:
 * open, put (loop), close
 *
 * @author radone@gmail.com
 */
class Csv
{

    /**
     *
     * @var string
     */
    protected $compression = '';

    /**
     *
     * @var string
     */
    protected $csv_delimiter = ',';

    /**
     *
     * @var string
     */
    protected $csv_enclosure = '"';

    /**
     *
     * @var string
     */
    protected $ext = 'csv';

    /**
     *
     * @var string
     */
    protected $filename = 'file';

    /**
     *
     * @var string
     */
    protected $mime_type = 'text/x-csv';

    /**
     *
     * @var resource pointer
     */
    protected $output;

    /**
     *
     * @var array
     */
    protected $table_headers;

    /**
     */
    public function __construct()
    {
        log_message('debug', "CSV Class Initialized");
    }

    protected function output_header()
    {
        if ($this->compression == 'zip') {
            if (@function_exists('gzcompress')) {
                $zipfile = new ZipArchive();
                $zipfile->addFile($buffer, $this->filename . $this->ext . '.gz');
                $buffer = $zipfile->file();

                $this->mime_type = 'application/x-zip';
                $this->ext .= '.zip';
            }
        } elseif ($this->compression == 'bzip') {
            if (@function_exists('bzcompress')) {
                $buffer = bzcompress($buffer);

                $this->mime_type = 'application/x-bzip';
                $this->ext .= '.bz2';
            }
        } elseif ($this->compression == 'gzip') {
            if (function_exists('gzencode')) {
                $buffer = gzencode($buffer);

                $this->mime_type = 'application/x-gzip';
                $this->ext .= '.gz';
            }
        }

        // send the headers and the file
        header('Content-Type: ' . $this->mime_type);
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        // IE needs specific headers
        if (preg_match('/MSIE ([0-9].[0-9]{1,2})/i', $_SERVER['HTTP_USER_AGENT'])) {
            header('Content-Disposition: inline; filename="' . $this->filename . '.' . $this->ext . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
        } else {
            header('Content-Disposition: attachment; filename="' . $this->filename . '.' . $this->ext . '"');
            header('Pragma: no-cache');
        }
    }

    /**
     *
     * @param array $params
     *            [
     *            table_headers - Array with table header columns name,
     *            ext - File extension,
     *            filename - File name (without extension)
     *            compression - Allowed values zip, bzip, gzip
     *            ]
     */
    public function open($params)
    {
        $allowed_params = [
            'table_headers',
            'ext',
            'filename',
            'compression'
        ];

        foreach ($allowed_params as $key) {
            if (property_exists($this, $key) && isset($params[$key])) {
                $this->$key = $params[$key];
            }
        }

        $this->output_header();

        $this->output = fopen('php://output', 'w');

        if (! empty($this->table_headers) && is_array($this->table_headers)) {
            fputcsv($this->output, $this->table_headers, $this->csv_delimiter, $this->csv_enclosure);
        }
    }

    /**
     *
     * @param array $table_data
     *            Raw data
     * @return mixed
     */
    public function put($table_data)
    {
        if (empty($table_data) || ! is_array($table_data)) {
            return false;
        }

        foreach ($table_data as $row) {
            if (! empty($row) && is_array($row)) {
                fputcsv($this->output, $row, $this->csv_delimiter, $this->csv_enclosure);
            }
        }
    }

    /**
     * Close stream
     */
    public function close()
    {
        fclose($this->output);
    }
}