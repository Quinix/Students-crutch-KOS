<?php
/* The MIT Licence
 *
 * Copyright (c) 2010 Jan langer <kontakt@janlanger.cz>
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Provides columns function for datagrid
 *
 * @author Jan Langer
 */
class DatagridColumn {

    private $key;
    private $title;
    private $formatter;

    public function __construct($key, $title) {
        $this->key = $key;
        $this->title = $title;
        $this->formatter = new DatagridFormatter();
    }

    /**
     * Set output formater for data.
     * @param string $type Type of formatter - use DatagridFormatter consts.
     * @param mixed $format Data for formatter
     * @see DatagridFormatter
     */
    public function setFormatter($type, $format) {
        $this->formatter = new DatagridFormatter($type, $format);
    }

    /**
     * Aplies format definition
     * @param mixed $data data
     * @return mixed
     */
    public function format($data) {
        return $this->formatter->format($data);
    }

    public function getKey() {
        return $this->key;
    }

    public function getTitle() {
        return $this->title;
    }

}

?>
