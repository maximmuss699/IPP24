<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
       


        try {
            
            //$domDocument = $this->source->getDOMDocument();
            //$xmlContent = $domDocument->saveXML();
            //$this->stdout->writeString($xmlContent);
            $domDocument = $this->source->getDOMDocument(); 
            $parsedInstructions = XmlParser::parse($domDocument);
            foreach ($parsedInstructions as $instruction) {
                
    
                foreach ($instruction['arguments'] as  $index => $argument) {
                //  $argInfo = "Instruction: Order={$instruction['order']}, Opcode={$instruction['opcode']}, Arg".($index + 1)." - Type={$argument['type']}, Value={$argument['value']}\n";                    
                 // $this->stdout->writeString($argInfo);

                
                }
            }
            $interpret = new Program($parsedInstructions);
            $interpret->run();
           // $this->stdout->writeString("Success\n");
            return 0; // 
        } catch (\Exception $e) {
            $this->stderr->writeString("Error: " . $e->getMessage() . "\n");
            //echo $e->getCode();
            exit($e->getCode());
        }
        throw new NotImplementedException;
    }


    
}
class XmlParser {
    public static function parse(\DOMDocument $domDocument): array
    {
        $root = $domDocument->documentElement;
        if ($root->tagName !== 'program') {
            throw new \Exception('Root element must be program', Errors::UNEXPECTED_XML_STRUCTURE);
        }
        $lang = $root->getAttribute('language');
        if ($lang !== 'IPPcode24') {
            throw new \Exception("Error: Attribute 'language' must be 'IPPcode24'.", Errors::WRONG_XML_FORMAT);
        }

        $instructions = $domDocument->getElementsByTagName('instruction');
        $parsedInstructions = [];
        $check_order2 = [];
        $varFrame = '';


        foreach ($instructions as $instruction) {
            $order = $instruction->getAttribute('order');
            //echo $order;
            if (!is_numeric($order)) {
                throw new \Exception('Order must be a number', Errors::UNEXPECTED_XML_STRUCTURE);
            }elseif ($order < 1) {
                throw new \Exception('Order must be a positive number', Errors::UNEXPECTED_XML_STRUCTURE);
            }
            $opcode = $instruction->getAttribute('opcode');
            if (empty($opcode)) {
                throw new \Exception('Opcode must not be empty', Errors::UNEXPECTED_XML_STRUCTURE);
            }elseif (!in_array($opcode, (new Opcodes())->opcode)) {
                throw new \Exception('Opcode is not valid ' . $opcode, Errors::UNEXPECTED_XML_STRUCTURE);
            }

            if (in_array($order, $check_order2)) {
                throw new \Exception('Duplicate order of instruction', Errors::UNEXPECTED_XML_STRUCTURE);
            }
            $check_order2[] = $order;
           

            $arguments = [];
            $check_order = [];

            foreach ($instruction->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $argOrder = $child->nodeName;
                    if (!in_array($argOrder, ['arg1', 'arg2', 'arg3'])) {
                        
                        throw new \Exception('Invalid argument order', Errors::UNEXPECTED_XML_STRUCTURE);
                    }
                    $argNum = intval(substr($child->nodeName, 3));
    
                    $argType = $child->getAttribute('type');
                    
                    $argValue = trim($child->textContent);
                    //echo $argValue;
                    if (empty($argType)) {
                        throw new \Exception('Argument type must not be empty', Errors::UNEXPECTED_XML_STRUCTURE);
                    }

                    if ($argType === 'var') {
                        list($varFrame, $varName) = explode('@', $argValue, 2);
                        if (!in_array($varFrame, ['GF', 'LF', 'TF'])) {
                            throw new \Exception('Invalid'. $varFrame . ' frame name', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                        if (!preg_match('/^([a-zA-Z]|[_\-\$&%\*])([a-zA-Z0-9]|[_\-\$&%\*])*$/',$varName)) {
                            throw new \Exception('Invalid variable name', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                    } elseif ($argType === 'label') {
                        if (!preg_match('/^([a-zA-Z]|[_\-\$&%\*])([a-zA-Z0-9]|[_\-\$&%\*])*$/',$argValue)) {
                            throw new \Exception('Invalid label name', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                    } elseif ($argType === 'type') {
                        if (!in_array($argValue, ['int', 'bool', 'string'])) {
                            throw new \Exception('Invalid type value', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                    } elseif ($argType === 'bool') {
                        if (!in_array($argValue, ['true', 'false'])) {
                            throw new \Exception('Invalid bool value', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                    } elseif ($argType === 'nil') {
                        if ($argValue !== 'nil') {
                            throw new \Exception('Invalid nil value', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                    } elseif ($argType === 'int') {
                        if (!preg_match('/^[-+]?[0-9]+$/',$argValue)) {
                            throw new \Exception('Invalid int value', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                    } elseif ($argType === 'string') {
                        if (!preg_match('/^([^#\\\\]|\\\\[0-9]{3})*$/',$argValue)) {
                            throw new \Exception('Invalid string value', Errors::UNEXPECTED_XML_STRUCTURE);
                        }
                        $argValue = self::stringEscape($argValue);
                    }
                    $arguments[$argNum] = ['type' => $argType, 'value' => $argValue, 'frame' => $varFrame, 'name' => $varName];
                    if (in_array($argOrder, $check_order)) // check if the order of arguments is not duplicated
                    {
                        throw new \Exception('Duplicate argument order', Errors::UNEXPECTED_XML_STRUCTURE);
                    }
                    $check_order[] = $argOrder; // store the order of arguments
                }
              
            }
            $args_length = [];
            if (in_array('arg1', $check_order)) {
                $args_length[] = 'arg1';
                if (in_array('arg2', $check_order)) {
                    $args_length[] = 'arg2';
                    if (in_array('arg3', $check_order)) {
                        $args_length[] = 'arg3';
                    }
                }
            }
            if (count($args_length) !== (new Arguments_length())->args_length[$opcode]) {
                throw new \Exception('Bad number of arguments for opcode', Errors::UNEXPECTED_XML_STRUCTURE);
            }

            
            ksort($arguments);
            
            

            $parsedInstructions[] = ['order' => $order, 'opcode' => $opcode, 'arguments' =>array_values($arguments)];
        }
        usort($parsedInstructions, [self::class, 'sortInstructionsByOrder']);

        return $parsedInstructions;
    }

    public static function stringEscape($string) {
        $pattern = '/\\\\(\d{3})/';
        $callback = function ($matches) {
            return chr($matches[1]);
        };
        return preg_replace_callback($pattern, $callback, $string);
    }
    private static function sortInstructionsByOrder($a, $b) {
        return $a['order'] - $b['order'];
    }

    






}

class Opcodes{
   public $opcode = [
    "MOVE","CREATEFRAME","PUSHFRAME","POPFRAME",
    "DEFVAR","CALL","RETURN","PUSHS","POPS","ADD","SUB","MUL","IDIV","LT","GT","EQ","AND","OR",
    "NOT","INT2CHAR","STRI2INT","READ","WRITE","CONCAT","STRLEN","GETCHAR","SETCHAR",
    "TYPE","LABEL","JUMP","JUMPIFEQ","JUMPIFNEQ","EXIT","DPRINT","BREAK"
   ]; 
    

}
class Arguments_length{
    public $args_length = [
        "MOVE" => 2,
        "CREATEFRAME" => 0,
        "PUSHFRAME" => 0,
        "POPFRAME" => 0,
        "DEFVAR" => 1,
        "CALL" => 1,
        "RETURN" => 0,
        "PUSHS" => 1,
        "POPS" => 1,
        "ADD" => 3,
        "SUB" => 3,
        "MUL" => 3,
        "IDIV" => 3,
        "LT" => 3,
        "GT" => 3,
        "EQ" => 3,
        "AND" => 3,
        "OR" => 3,
        "NOT" => 2,
        "INT2CHAR" => 2,
        "STRI2INT" => 3,
        "READ" => 2,
        "WRITE" => 1,
        "CONCAT" => 3,
        "STRLEN" => 2,
        "GETCHAR" => 3,
        "SETCHAR" => 3,
        "TYPE" => 2,
        "LABEL" => 1,
        "JUMP" => 1,
        "JUMPIFEQ" => 3,
        "JUMPIFNEQ" => 3,
        "EXIT" => 1,
        "DPRINT" => 1,
        "BREAK" => 0
    ];


}
class Errors {
     # Program
    const MISSING_PARAMETER = 10;
    const INPUT_OPEN = 11;
    const OUTPUT_OPEN = 12;
     # Parser
    const WRONG_MISSING_HEADER = 21;
    const WRONG_OPCODE = 22;
    const OTHER_LEX_SYNT = 23;
    const WRONG_XML_FORMAT = 31;
    const UNEXPECTED_XML_STRUCTURE = 32;
    const SEMANTIC_CHECKS = 52;
    const WRONG_OPERAND_TYPE = 53;
    const NONEXISTENT_VARIABLE = 54;
    const NONEXISTENT_FRAME = 55;
    const MISSING_VALUE = 56;
    const WRONG_OPERANT_VALUE = 57;
    const WRONG_STRING = 58;
    const INTEGRATION_ERROR = 88;
}



class Program{
    public $instructions = [];  
    public $GF = [];
    public $LF = [];
    public $TF_activator = 1;
    public $TF = [];
    public $dataStack = [];
    public $callStack = [];
    public $instructionPointer = 0;
    public $instructionCount = 0;
    public $labels = [];
    public $variables = [];
    public function __construct($instructions){
        $this->instructions = $instructions;       
     
    }

    public function run(){
       //var_dump($this->instructions);
       $this->_getLabels($this->instructions);
       $this->instructionPointer = 0;
       //echo "Instruction count: " . count($this->instructions) . "\n";
       while ($this->instructionPointer < count($this->instructions)) {
            $this->instructions[$this->instructionPointer];
            $opcode = $this->instructions[$this->instructionPointer]['opcode'];
          //  echo "Instruction: " . $this->instructions[$this->instructionPointer]['opcode'] . "\n";
            if ($opcode === 'DEFVAR') {
                $this->_defvar();
            }

            else if ($opcode === 'MOVE') {
                $var_Name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $var_Name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
               
                if ($type === 'var') {
                    $this->_check($var_Name, $frame);
                } 
                if ($type2 === 'var') {
                    $this->_check($var_Name2, $frame2);
                } 
                $this->_change_var();
                //echo "GF: \n";
               // var_dump($this->GF);
            }
            else if ($opcode === 'ADD' 
            || $opcode === 'MUL' 
            || $opcode === 'SUB' 
            || $opcode === 'IDIV'
            || $opcode === 'LT'
            || $opcode === 'GT'
            || $opcode === 'EQ' 
            ) {
                $name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                $name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
                $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];

                $this->_check($name1,$frame);
                if ($type2 === 'var') {
                    $this->_check($name2, $frame2);
                } 
                if ($type3 === 'var') {
                    $this->_check($name3, $frame3 );
                }

                $this->_arithmetic();
            }else if($opcode === 'CONCAT'){
                $name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                $name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
                $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];

                $this->_check($name1,$frame);
                if ($type2 === 'var') {
                    $this->_check($name2, $frame2);
                } 
                if ($type3 === 'var') {
                    $this->_check($name3, $frame3 );
                }

                $this->_strings();


            }else if($opcode === 'AND' || $opcode === 'OR' || $opcode === 'NOT'){
                $name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                if ($opcode === 'NOT') {
                    $frame3 = null;
                    $type3 = null;
                    $name3 = null;
                }else{
                        $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
                        $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
                        $name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
                    }
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];

                $this->_check($name1,$frame);
                if ($type2 === 'var') {
                    $this->_check($name2, $frame2);
                } 
                if ($type3 === 'var') {
                    $this->_check($name3, $frame3 );
                }

                $this->_logic();
            }
            
           
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'WRITE') {
               
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $var_name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $var_value = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
            
                if(isset($this->{$frame})){
                    //var_dump($this->{$frame});
                }
                $value = $this->{$frame}[$var_name];
                if($type === 'var'){
                        $this->_check($var_name,$frame);
                        
                        if( $this->{$frame}[$var_name] === null){
                            throw new \Exception('Missing value', Errors::MISSING_VALUE);
                        }else{
                            if($this->{$frame}[$var_name] === 'nil'){
                            }
                         
                            else{
                                switch (gettype($value)) {
                                    case "boolean":
                                        echo $value ? "true" : "false";
                                        break;
                                    case "integer":
                                        echo $this->{$frame}[$var_name];
                                        break;
                                    case "string":
                                        echo $this->{$frame}[$var_name];
                                       //($this->{$frame});
                                            break;
                                    }
                            
                            }
                        }
                     

                }else if($type === 'bool'){
                    print $var_value . "\n";
                }else if($type === 'nil'){
                     
                    
                }
                else {
                    
                    print $var_value . "\n";
                    
                
                }

            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'JUMP') {
               
                $label = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                $label_index = $this->findLabelIndex($label);
                if ($label_index === -1) {
                    throw new \Exception('Label does not exist', Errors::SEMANTIC_CHECKS);
                }else{
                    $this->instructionPointer = $label_index;
                }

            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'JUMPIFEQ') {
                $name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                $name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
                $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
                

                $this->_check($name1,$frame);
                if ($type2 === 'var') {
                    $this->_check($name2, $frame2);
                } 
                if ($type3 === 'var') {
                    $this->_check($name3, $frame3 );
                }

                $value2 = ($type2 === 'var') ? $this->{$frame2}[$name2] : $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
                $value3 = ($type3 === 'var') ? $this->{$frame3}[$name3] : $this->instructions[$this->instructionPointer]['arguments'][2]['value'];
                $value2 = (int)$value2;
                $value3 = (int)$value3;
                if($value2 === $value3){
                    $label = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                    $label_index = $this->findLabelIndex($label);
                    if ($label_index === -1) {
                        throw new \Exception('Label does not exist', Errors::SEMANTIC_CHECKS);
                    }else{
                        $this->instructionPointer = $label_index;
                    }
                }
              
               
                
            
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'CREATEFRAME') {
            $this->TF_activator = 0;
           
            $this->TF = []; // 
           // var_dump($this->TF);
           // var_dump($this->LF);

          
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'PUSHFRAME') {
            
                if ($this->TF_activator === 0){
                    $this->TF_activator = 1;
                   // array_push($this->LF, $this->TF);
                   if($this->TF !== null){
                    $this->LF = array_merge($this->LF, $this->TF);
                   }else{
                          throw new \Exception('Frame is not defined', Errors::NONEXISTENT_FRAME);
                   }
                   // echo "LF: \n";
                    //var_dump($this->LF);

                    $this->TF = null;
                    
                }else{
                    throw new \Exception('Temporary frame is not defined', Errors::NONEXISTENT_FRAME);
                }
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'POPFRAME') {
               
                    $this->TF_activator = 0;
                    
                    if (!empty($this->LF)){
                        $this->TF = $this->LF;
                        
                        //echo "TF: \n";
                        //var_dump($this->TF);
                    }
                    else{
                        throw new \Exception('LOcal frame is not defined', Errors::NONEXISTENT_FRAME);
                        }
            }
            
            
            
            $this->instructionPointer++;
       }

    }
    private function _getLabels($instructions) {
        foreach ($instructions as $instruction) {
            if ($instruction['opcode'] === 'LABEL') {
                $label = $instruction['arguments'][0]['value']; // get the label name
                if (array_key_exists($label, $this->labels)) {
                    throw new \Exception('Duplicate label', Errors::SEMANTIC_CHECKS);
                }
                $this->labels[$label] = $this->instructionPointer; // store the label name and its position
            }
        }
        
    }
    private function _defvar(){
        $var_name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        //$var_value = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
        //echo "Var: " . $var_value . "\n";
       // echo "Frame: " . $frame . "\n";
        $this->variables[$frame][$var_name] = NULL; 
        if ($frame === 'GF') {
            if (array_key_exists($var_name, $this->GF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
           
            $this->GF[$var_name] = null;
           // echo "frame: " . $frame . "\n";
           // var_dump($this->GF);
        } elseif ($frame === 'LF') {
            if (array_key_exists($var_name, $this->LF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
            $this->LF[$var_name] = null;
            //echo "frame: " . $frame . "\n";
           // var_dump($this->{$frame});
            
        } elseif ($frame === 'TF') {
            if (array_key_exists($var_name, $this->TF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
            
            if ($this->TF_activator === 1)
            {
                throw new \Exception('Frame is not defined', Errors::NONEXISTENT_FRAME);
            }
            $this->TF[$var_name] = null;
           // echo "frame: " . $frame . "\n";
           //($this->{$frame});
            
        }
    }
    private function _check($varName, $frame){
        
    
        if ($frame === 'GF') {
            if (!array_key_exists($varName, $this->GF)) {
                throw new \Exception('Variable ' . $varName . ' does not exist in GF frame CHECK', Errors::NONEXISTENT_VARIABLE);
            }
            
        } elseif ($frame === 'LF') {
            if (!array_key_exists($varName, $this->LF)) {
                throw new \Exception('Variable does not exist in LF frame CHECK', Errors::NONEXISTENT_VARIABLE);
            }
            
        } elseif ($frame === 'TF') {
            if (isset($this->TF) && !empty($this->TF)){
            if (!array_key_exists($varName, $this->TF)) {
                throw new \Exception('Variable does not exist in TF frame CHECK', Errors::NONEXISTENT_VARIABLE);
            }
        }else{
            throw new \Exception('Valuable is not defined', Errors::NONEXISTENT_VARIABLE);
        }
           
        }
    
    }
   
    
    private function _change_var(){
        if (isset($this->instructions[$this->instructionPointer]['arguments'][0]) && 
        isset($this->instructions[$this->instructionPointer]['arguments'][1])) {

        $arg1Value = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $arg2Value = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
        $arg2Name = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
        //echo "argument 2 value";
        //var_dump($arg2Value);

        if ($type2 === 'var') {
            $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
            $arg2Value = $this->{$frame2}[$arg2Name];  // Получаем значение переменной arg2 из ее фрейма
        } else {
            $arg2Value = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];  // Используем значение напрямую, если это не переменная
        }
        if ($frame === 'GF') { 
            $this->GF[$arg1Value] = $arg2Value;
        } elseif ($frame === 'LF') {
           
            $this->LF[$arg1Value] = $arg2Value;
        } elseif ($frame === 'TF') {
          
            $this->TF[$arg1Value] = $arg2Value;
        }
        
        //echo "frame: " . $frame . "\n";
       // var_dump($this->{$frame});
        
           
    } else {
        throw new \Exception('Missing value', Errors::MISSING_VALUE);
    }

    }


    private function _arithmetic(){
        $arg_name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $arg_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
        $arg_name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
        $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
        $opcodeArg = $this->instructions[$this->instructionPointer]['opcode'];
        
        

        $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
        $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
        $value2 = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        $value3 = $this->instructions[$this->instructionPointer]['arguments'][2]['value'];

        
        $value2 = ($type2 === 'var') ? $this->{$frame2}[$arg_name2] : $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        $value3 = ($type3 === 'var') ? $this->{$frame3}[$arg_name3] : $this->instructions[$this->instructionPointer]['arguments'][2]['value'];

        
        if ( $opcodeArg === 'ADD' || $opcodeArg === 'MUL' || $opcodeArg === 'SUB' || $opcodeArg === 'IDIV'){
        if (is_numeric($value2) && is_numeric($value3) && $value2 == (int)$value2  && $value3 == (int)$value3) {
            $value2 = (int)$value2;
            $value3 = (int)$value3;
        } else {
            throw new \Exception("Values for ADD operation are not integers", Errors::WRONG_OPERAND_TYPE);
        }
        }
        if ( $opcodeArg === 'LT' || $opcodeArg === 'GT' || $opcodeArg === 'EQ'){
            // Используем функцию areValuesComparable для проверки типов значений
    if (!$this->areValuesComparable($value2, $value3)) {
        throw new \Exception("Wrong operands, types do not match", Errors::WRONG_OPERAND_TYPE);
    }

    // Теперь значения можно сравнивать, т.к. они приведены к одному типу
    $value2 = $this->convertString($value2);
    $value3 = $this->convertString($value3);

    // Сравнение значений
    switch ($opcodeArg) {
        case 'LT':
            $result = $value2 < $value3;
            break;
        case 'GT':
            $result = $value2 > $value3;
            break;
        case 'EQ':
            $result = $value2 === $value3;
            break;
    }

    // Запись результата в переменную
    if (!array_key_exists($arg_name1, $this->{$frame})) {
        throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
    }
    $this->{$frame}[$arg_name1] = $result;
        }

        if ( $opcodeArg === 'ADD'){
        $result = $value2 + $value3;
        }else if ( $opcodeArg  === 'MUL'){
            $result = $value2 * $value3;
        }else if ( $opcodeArg  === 'SUB'){
            $result = $value2 - $value3;
        }else if ( $opcodeArg  === 'IDIV'){
            if ($value3 === 0) {
                throw new \Exception("Division by zero", Errors::WRONG_OPERANT_VALUE);
            }else{
            $result = $value2 / $value3;
            }
        }
    



        if (!array_key_exists($arg_name1, $this->{$frame})) {
            throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
        }
        $this->{$frame}[$arg_name1] = $result;
       // echo "Result: " .  $this->{$frame}[$arg_name1] . "\n";
        
      // var_dump($this->GF);
        
    }

    private function findLabelIndex($labelName) {
        foreach ($this->instructions as $index => $instruction) {
            if ($instruction['opcode'] === 'LABEL' && $instruction['arguments'][0]['value'] === $labelName) {
                //echo "Label: " . $labelName . "\n";
                //echo "Index: " . $index . "\n";
                return $index;
            }
           
        }
        return -1; // Если метка не найдена
    }

    private function _strings(){
        $arg_name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $arg_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
        $arg_name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
        $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
        $opcodeArg = $this->instructions[$this->instructionPointer]['opcode'];
        
        

        $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
        $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
        $value2 = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        $value3 = $this->instructions[$this->instructionPointer]['arguments'][2]['value'];

        
        $value2 = ($type2 === 'var') ? $this->{$frame2}[$arg_name2] : $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        $value3 = ($type3 === 'var') ? $this->{$frame3}[$arg_name3] : $this->instructions[$this->instructionPointer]['arguments'][2]['value'];
       //var_dump($value2);
       //var_dump($value3);
        if ($opcodeArg === 'CONCAT') {
            if ($this->checkVariableString($value2) && $this->checkVariableString($value3)) {
                $result = $value2 . $value3;
            } else {
                throw new \Exception("Values for CONCAT operation are not strings", Errors::WRONG_OPERAND_TYPE);
            }
        }
        if (!array_key_exists($arg_name1, $this->{$frame})) {
            throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
        }
        $this->{$frame}[$arg_name1] = $result;
       // echo "Result: " .  $this->{$frame}[$arg_name1] . "\n";
        
      // var_dump($this->GF);
        
    }

    private function _logic(){
        $arg_name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $arg_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];   
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];  
        $opcodeArg = $this->instructions[$this->instructionPointer]['opcode'];
        
        
        $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
        $value2 = ($type2 === 'var') ? $this->{$frame2}[$arg_name2] : $this->instructions[$this->instructionPointer]['arguments'][1]['value'];

        if ($opcodeArg === 'NOT') {
            $value3 = null;
            $type3 = null;
            $arg_name3 = null;
            $frame3 = null;
            $value2 = $value2 === 'true' ? true : ($value2 === 'false' ? false : $value2);
            if (is_bool($value2)) {
                $value2 = $value2 === 'true' ? true : ($value2 === 'false' ? false : $value2);
                $result = !$value2;
                if (!array_key_exists($arg_name1, $this->{$frame})) {
                    throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
                }
                $this->{$frame}[$arg_name1] = $result;
               
            } else {
                throw new \Exception("Wrong type of operand", Errors::WRONG_OPERAND_TYPE);
            }
        }else{
            $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
            $arg_name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
            $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
            $value3 = ($type3 === 'var') ? $this->{$frame3}[$arg_name3] : $this->instructions[$this->instructionPointer]['arguments'][2]['value'];
            $value2 = $value2 === 'true' ? true : ($value2 === 'false' ? false : $value2);
            $value3 = $value3 === 'true' ? true : ($value3 === 'false' ? false : $value3);
            if ( $opcodeArg === 'AND'){
                $value2 = $value2 === 'true' ? true : ($value2 === 'false' ? false : $value2);
                $value3 = $value3 === 'true' ? true : ($value3 === 'false' ? false : $value3);
                if (is_bool($value2) && is_bool($value3)) {
                    $result = $value2 && $value3;
                    if (!array_key_exists($arg_name1, $this->{$frame})) {
                        throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
                    }
                    $this->{$frame}[$arg_name1] = $result;
                } else {
                    throw new \Exception("Values for AND operation are not booleans", Errors::WRONG_OPERAND_TYPE);
                }
        }else if($opcodeArg === 'OR'){
            if (is_bool($value2) && is_bool($value3)) {
                $result = $value2 || $value3;
                if (!array_key_exists($arg_name1, $this->{$frame})) {
                    throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
                }
                $this->{$frame}[$arg_name1] = $result;
            } else {
                throw new \Exception("Values for AND operation are not booleans", Errors::WRONG_OPERAND_TYPE);
            }



        }


        


    }
}

    private function checkVariableString($value) {
        if ($value === 'nil') {
           return false;
            
        } elseif ($value === 'true' || $value === 'false') {
            return false;
        }
        elseif (is_numeric($value)) {
            return false;}
        else 
        {
            if (is_string($value)) {
            return true;
        }
    }
    }
    private function checkVariablearithmetic($value) {
        if (is_bool($value)) {
           return 1;
            
        } elseif (is_null($value)) {
            return 1;
        }
        
        else {
            if (is_string($value)) {
            return 0;
        }
    }
    }

    public function convertString($value) {
        if (is_numeric($value)) {
            return $value + 0;
        }
        if (strtolower($value) === "true") {
            return true;
        }
        if (strtolower($value) === "false") {
            return false;
        }
        return $value;
    }
    
   private function areValuesComparable($value1, $value2) {
        $convertedValue1 = $this->convertString($value1);
        $convertedValue2 = $this->convertString($value2);
    
        return gettype($convertedValue1) === gettype($convertedValue2);
    }
    




}
