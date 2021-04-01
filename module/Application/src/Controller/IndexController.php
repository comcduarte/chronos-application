<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Entity\UserEntity;
use Files\Model\FilesModel;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Http\Request as HttpRequest;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Exception;
use Files\Traits\FilesAwareTrait;

class IndexController extends AbstractActionController
{
    use AdapterAwareTrait;
    use FilesAwareTrait;
    
    public function indexAction()
    {
        $view = new ViewModel();
        return $view;
    }
    
    public function cronAction()
    {
        $request = $this->getRequest();
        $view = new ViewModel();
        
        $num_files = 999999;
        $runtime = 20; // seconds
        $start_time = NULL;
        $end_time = NULL;
        
        /******************************
         * Process Files in Queue
         ******************************/
        $fi = new \FilesystemIterator('./data/queue/', \FilesystemIterator::SKIP_DOTS);
        
        while ($fi->valid() && $num_files > 0) {
            $this->tick($start_time, $end_time, $runtime, $num_files);
            
            $filename = $fi->getFileName();
            $filepath = './data/queue/' . $filename;
            $matches = [];
            
            $user_entity = new UserEntity($this->adapter);
            
            switch (TRUE) {
                /******************************
                 * Filter out W2 Forms and 1095C Forms
                 * i.e.  W2_2018_1_007440_1902111135.pdf
                 * i.e.  1095C_2019_1_010959_2006161336.pdf
                 ******************************/
                case preg_match('/.*\_(\d*)\_\d*/', $filename, $matches):
                    if (!$user_entity->employee->read(['EMP_NUM' => $matches[1]])) {
                        //** Error **//
                        $this->flashMessenger()->addErrorMessage('Unable to read by EMP_NUM: ' . $matches[1]);
                        $fi->next();
                        continue 2;
                    }
                    
                    if (!$user_entity->getEmployee($user_entity->employee->UUID)) {
                        //** Error **//
                        $this->flashMessenger()->addErrorMessage('Unable to get Employee: ' . $user_entity->employee->UUID);
                        $fi->next();
                        continue 2;
                    }
                    break;
                    
                    /******************************
                     * Filter out Paystubs
                     * i.e.  HR_CITZ0038254160331.pdf
                     * HR_CITZ0 | 038254 | 160331.pdf
                     ******************************/
                case preg_match('/.*(\d{6})(\d{6})/', $filename, $matches):
                    $check = $matches[1];
                    $warrant = $matches[2];
                    
//                     $text = $this->pdf2text($filepath);
//                     $emp_matches = [];
//                     preg_match('/Employee # (\d{6})/', $text, $emp_matches);
//                     $this->flashMessenger()->addInfoMessage($emp_matches[1]);

                    
                    //----------------------------------------------------------------------
                    $infile = @file_get_contents($filepath);
                    if (empty($infile)) {
                        continue 2;
                    }
                    
                    /**
                     * Retrieve list of objects
                     */
                    preg_match_all("#obj(.*)endobj#ismU", $infile, $objects);
                    $objects = @$objects[1];
                    
                    $data['count_objects'] = count($objects);
                    
                    for ($i = 0; $i < count($objects); $i++) {
                        $currentObject = $objects[$i];
                        
                        $options = $this->getObjectOptions($currentObject);
                        
                        if (!empty($options['Subtype']) && $options['Subtype'] == 'Image') {
                            continue ;
                        }
                        
                        $data[$i]['options'] = $options;
                        
                        $stream = [];
                        $empnum = [];
                        
                        switch (TRUE) {
                            case preg_match("#stream(.*)endstream#ismU", $currentObject, $stream):
                                $stream = ltrim($stream[1]);
                                $data[$i]['stream'] = $stream;
                                $data[$i]['decoded stream'] = $this->getDecodedStream($stream, $options);
                                if (preg_match('/Employee \# (\d{6})/', $data[$i]['decoded stream'], $empnum)) {
                                    $employee = $empnum[1];
//                                     $this->flashMessenger()->addInfoMessage($employee);
                                }
                                break;
                            case preg_match("#\[(.*)\]#ismU", $currentObject, $stream):
                                $data[$i]['stream'] = $stream[1];
                                break;
                            default:
                                $data[$i]['stream'] = '<unknown>';
                                break;
                        }
                        
                        
                        
                    }
                    //------------------------------------------------------------------
                    
                    $emp_num = $employee;
                    
                    if (!$user_entity->employee->read(['EMP_NUM' => $emp_num])) {
                        //** Error **//
                        $this->flashMessenger()->addErrorMessage('Unable to read by EMP_NUM: ' . $emp_num);
                        $fi->next();
                        continue 2;
                    }
                    
                    if (!$user_entity->getEmployee($user_entity->employee->UUID)) {
                        //** Error **//
                        $this->flashMessenger()->addErrorMessage('Unable to get Employee: ' . $user_entity->employee->UUID);
                        $fi->next();
                        continue 2;
                    }
                    
                    $this->flashMessenger()->addInfoMessage("Paystub: Check Number: $check | Warrant: $warrant | Employee #: $emp_num");
//                     $fi->next();
//                     continue 2;
                    break;
                default:
                    $this->flashMessenger()->addInfoMessage("No Case Applied: $filename");
                    $fi->next();
                    continue 2;
                    break;
            }
            
            $files = new FilesModel();
            $files->setDbAdapter($this->getFilesAdapter());
            $files->NAME = $filename;
            $files->SIZE = filesize($filepath);
            $files->TYPE = mime_content_type($filepath);
            $files->REFERENCE = $user_entity->employee->UUID;
            $files->BLOB = file_get_contents($filepath);
            
            try {
                $files->create();
            } catch (Exception $e) {
                return FALSE;
            }
            
            unlink($filepath);
            $fi->next();
        }
        
        if ($request instanceof HttpRequest) {
            $url = $this->getRequest()->getHeader('Referer')->getUri();
            return $this->redirect()->toUrl($url);
        }
        
    }
    
    public function testAction()
    {
        $view = new ViewModel();
        $data = [];
        $objects = [];
        
        $fi = new \FilesystemIterator('./data/queue/', \FilesystemIterator::SKIP_DOTS);
        
        foreach ($fi as $file) {
            $filename = $file->getFileName();
            $filepath = './data/queue/' . $filename;
            
            $infile = @file_get_contents($filepath);
            if (empty($infile)) {
                continue;
            }
                
            /**
             * Retrieve list of objects
             */
            preg_match_all("#obj(.*)endobj#ismU", $infile, $objects);
            $objects = @$objects[1];
            
            $data['count_objects'] = count($objects);
            
            for ($i = 0; $i < count($objects); $i++) {
                $currentObject = $objects[$i];
                
                $options = $this->getObjectOptions($currentObject);
                
                if (!empty($options['Subtype']) && $options['Subtype'] == 'Image') {
                    continue ;
                }
                
                $data[$i]['options'] = $options;
                
                $stream = [];
                
                switch (TRUE) {
                    case preg_match("#stream(.*)endstream#ismU", $currentObject, $stream):
                        $stream = ltrim($stream[1]);
                        $data[$i]['stream'] = $stream;
                        $data[$i]['decoded stream'] = $this->getDecodedStream($stream, $options);
                        if (preg_match('/Employee \# (\d{6})/', $data[$i]['decoded stream'], $empnum)) {
                            $employee = $empnum[1];
                        }
                        break;
                    case preg_match("#\[(.*)\]#ismU", $currentObject, $stream):
                        $data[$i]['stream'] = $stream[1];
                        break;
                    default:
                        $data[$i]['stream'] = '<unknown>';
                        break;
                }
                
                
                
            }
            
        }
        
        $view->setVariable('data', $data);
        return $view;
    }
    
    private function tick(&$start_time, &$end_time, &$runtime, &$num_files)
    {
        if (is_null($start_time) && is_null($end_time)) {
            $start_time = microtime(TRUE);
            $num_files = 999999;
            return;
        }
        
        if (is_null($end_time) && $num_files == 999999) {
            $end_time = microtime(TRUE);
            $num_files = $runtime / ($end_time - $start_time);
            $this->flashMessenger()->addInfoMessage('Recommended number of files to process: ' . $num_files);
            return;
        }
        
        if ($num_files < 999999) {
            $num_files--;
            return;
        }
    }
    
    private function decodeAsciiHex($input) {
        $output = "";
        
        $isOdd = true;
        $isComment = false;
        
        for($i = 0, $codeHigh = -1; $i < strlen($input) && $input[$i] != '>'; $i++) {
            $c = $input[$i];
            
            if($isComment) {
                if ($c == '\r' || $c == '\n')
                    $isComment = false;
                    continue;
            }
            
            switch($c) {
                case '\0': case '\t': case '\r': case '\f': case '\n': case ' ': break;
                case '%':
                    $isComment = true;
                    break;
                    
                default:
                    $code = hexdec($c);
                    if($code === 0 && $c != '0')
                        return "";
                        
                        if($isOdd)
                            $codeHigh = $code;
                            else
                                $output .= chr($codeHigh * 16 + $code);
                                
                                $isOdd = !$isOdd;
                                break;
            }
        }
        
        if($input[$i] != '>')
            return "";
            
            if($isOdd)
                $output .= chr($codeHigh * 16);
                
                return $output;
    }
    
    private function decodeAscii85($input) {
        $output = "";
        
        $isComment = false;
        $ords = array();
        
        for($i = 0, $state = 0; $i < strlen($input) && $input[$i] != '~'; $i++) {
            $c = $input[$i];
            
            if($isComment) {
                if ($c == '\r' || $c == '\n')
                    $isComment = false;
                    continue;
            }
            
            if ($c == '\0' || $c == '\t' || $c == '\r' || $c == '\f' || $c == '\n' || $c == ' ')
                continue;
                if ($c == '%') {
                    $isComment = true;
                    continue;
                }
                if ($c == 'z' && $state === 0) {
                    $output .= str_repeat(chr(0), 4);
                    continue;
                }
                if ($c < '!' || $c > 'u')
                    return "";
                    
                    $code = ord($input[$i]) & 0xff;
                    $ords[$state++] = $code - ord('!');
                    
                    if ($state == 5) {
                        $state = 0;
                        for ($sum = 0, $j = 0; $j < 5; $j++)
                            $sum = $sum * 85 + $ords[$j];
                            for ($j = 3; $j >= 0; $j--)
                                $output .= chr($sum >> ($j * 8));
                    }
        }
        if ($state === 1)
            return "";
            elseif ($state > 1) {
                for ($i = 0, $sum = 0; $i < $state; $i++)
                    $sum += ($ords[$i] + ($i == $state - 1)) * pow(85, 4 - $i);
                    for ($i = 0; $i < $state - 1; $i++)
                        $ouput .= chr($sum >> ((3 - $i) * 8));
            }
            
            return $output;
    }
    
    private function decodeFlate($input) {
        return @gzuncompress($input);
    }
    
    private function getObjectOptions($object) {
        $options = array();
        if (preg_match("#<<(.*)>>#ismU", $object, $options)) {
            $options = explode("\n", trim($options[1]));
//             @array_shift($options);

            $o = array();
            for ($j = 0; $j < @count($options); $j++) {
                $options[$j] = preg_replace("#\s+#", " ", trim($options[$j]));
                if (strpos($options[$j], " ") !== false) {
                    $parts = explode(" ", $options[$j]);
                    $o[str_replace('/','',$parts[0])] = str_replace('/','',$parts[1]);
                } else
                    $o[$options[$j]] = true;
            }
            $options = $o;
            unset($o);
        }
        
        return $options;
    }
    
    private function getDecodedStream($stream, $options) {
        $data = "";
        if (empty($options["Filter"]))
            $data = $stream;
            else {
                $length = !empty($options["Length"]) ? intval($options["Length"]) : strlen($stream);
                $_stream = substr($stream, 0, $length);
                
                foreach ($options as $key => $value) {
                    switch (TRUE) {
                        case ($value == "[ASCIIHexDecode]"):
                            $_stream = $this->decodeAsciiHex($_stream);
                            break;
                        case ($value == "[ASCII85Decode]"):
                            $_stream = $this->decodeAscii85($_stream);
                            break;
                        case ($value == "[FlateDecode]"):
                            $_stream = $this->decodeFlate($_stream);
                            break;
                        default:
                            break;
                    }
                }
                $data = $_stream;
            }
            return $data;
    }
    
    private function getDirtyTexts(&$texts, $textContainers) {
        for ($j = 0; $j < count($textContainers); $j++) {
            if (preg_match_all("#\[(.*)\]\s*TJ#ismU", $textContainers[$j], $parts))
                $texts = array_merge($texts, @$parts[1]);
                elseif(preg_match_all("#Td\s*(\(.*\))\s*Tj#ismU", $textContainers[$j], $parts))
                $texts = array_merge($texts, @$parts[1]);
        }
    }
    
    private function getCharTransformations(&$transformations, $stream) {
        preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU", $stream, $chars, PREG_SET_ORDER);
        preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU", $stream, $ranges, PREG_SET_ORDER);
        
        for ($j = 0; $j < count($chars); $j++) {
            $count = $chars[$j][1];
            $current = explode("\n", trim($chars[$j][2]));
            for ($k = 0; $k < $count && $k < count($current); $k++) {
                if (preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is", trim($current[$k]), $map))
                    $transformations[str_pad($map[1], 4, "0")] = $map[2];
            }
        }
        for ($j = 0; $j < count($ranges); $j++) {
            $count = $ranges[$j][1];
            $current = explode("\n", trim($ranges[$j][2]));
            for ($k = 0; $k < $count && $k < count($current); $k++) {
                if (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+<([0-9a-f]{4})>#is", trim($current[$k]), $map)) {
                    $from = hexdec($map[1]);
                    $to = hexdec($map[2]);
                    $_from = hexdec($map[3]);
                    
                    for ($m = $from, $n = 0; $m <= $to; $m++, $n++)
                        $transformations[sprintf("%04X", $m)] = sprintf("%04X", $_from + $n);
                } elseif (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+\[(.*)\]#ismU", trim($current[$k]), $map)) {
                    $from = hexdec($map[1]);
                    $to = hexdec($map[2]);
                    $parts = preg_split("#\s+#", trim($map[3]));
                    
                    for ($m = $from, $n = 0; $m <= $to && $n < count($parts); $m++, $n++)
                        $transformations[sprintf("%04X", $m)] = sprintf("%04X", hexdec($parts[$n]));
                }
            }
        }
    }
    
    private function getTextUsingTransformations($texts, $transformations) {
        $document = "";
        for ($i = 0; $i < count($texts); $i++) {
            $isHex = false;
            $isPlain = false;
            
            $hex = "";
            $plain = "";
            for ($j = 0; $j < strlen($texts[$i]); $j++) {
                $c = $texts[$i][$j];
                switch($c) {
                    case "<":
                        $hex = "";
                        $isHex = true;
                        break;
                    case ">":
                        $hexs = str_split($hex, 4);
                        for ($k = 0; $k < count($hexs); $k++) {
                            $chex = str_pad($hexs[$k], 4, "0");
                            if (isset($transformations[$chex]))
                                $chex = $transformations[$chex];
                                $document .= html_entity_decode("&#x".$chex.";");
                        }
                        $isHex = false;
                        break;
                    case "(":
                        $plain = "";
                        $isPlain = true;
                        break;
                    case ")":
                        $document .= $plain;
                        $isPlain = false;
                        break;
                    case "\\":
                        $c2 = $texts[$i][$j + 1];
                        if (in_array($c2, array("\\", "(", ")"))) $plain .= $c2;
                        elseif ($c2 == "n") $plain .= '\n';
                        elseif ($c2 == "r") $plain .= '\r';
                        elseif ($c2 == "t") $plain .= '\t';
                        elseif ($c2 == "b") $plain .= '\b';
                        elseif ($c2 == "f") $plain .= '\f';
                        elseif ($c2 >= '0' && $c2 <= '9') {
                            $oct = preg_replace("#[^0-9]#", "", substr($texts[$i], $j + 1, 3));
                            $j += strlen($oct) - 1;
                            $plain .= html_entity_decode("&#".octdec($oct).";");
                        }
                        $j++;
                        break;
                        
                    default:
                        if ($isHex)
                            $hex .= $c;
                            if ($isPlain)
                                $plain .= $c;
                                break;
                }
            }
            $document .= "\n";
        }
        
        return $document;
    }
    
    private function pdf2text($filename) {
        // CDD: Deprectated $infile = @file_get_contents($filename, FILE_BINARY);
        $infile = @file_get_contents($filename);
        if (empty($infile))
            return "";
            
            $transformations = array();
            $texts = array();
            
            preg_match_all("#obj(.*)endobj#ismU", $infile, $objects);
            $objects = @$objects[1];
            
            for ($i = 0; $i < count($objects); $i++) {
                $currentObject = $objects[$i];
                
                if (preg_match("#stream(.*)endstream#ismU", $currentObject, $stream)) {
                    $stream = ltrim($stream[1]);
                    
                    $options = $this->getObjectOptions($currentObject);
                    //-- CDD: Removed 1 at end of Length
                    if (!(empty($options["Length"]) && empty($options["Type"]) && empty($options["Subtype"])))
                        //continue;
                        
                        $data = $this->getDecodedStream($stream, $options);
                        if (is_null($data)) {
                            continue;
                        }
                        
                        if (strlen($data)) {
                            if (preg_match_all("#BT(.*)ET#ismU", $data, $textContainers)) {
                                $textContainers = @$textContainers[1];
                                $this->getDirtyTexts($texts, $textContainers);
                            } else {
                                $this->getCharTransformations($transformations, $data);
                            }
                        }
                }
            }
            
            return $this->getTextUsingTransformations($texts, $transformations);
    }
    
}
