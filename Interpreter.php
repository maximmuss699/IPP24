<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
       
        try {
            
            
            $domDocument = $this->source->getDOMDocument(); 
            $parsedInstructions = XmlParser::parse($domDocument);
            $interpret = new Program($parsedInstructions, $this->input, $this->stdout);
            $interpret->run();
           
            return 0; // 
        } catch (\Exception $e) {
            $this->stderr->writeString("Error: " . $e->getMessage() . "\n");
            //echo $e->getCode();
            exit($e->getCode());
        }
       
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
            throw new \Exception("Error: Attribute 'language' must be 'IPPcode24'.", Errors::UNEXPECTED_XML_STRUCTURE);
        }

        $instructions = $domDocument->getElementsByTagName('instruction');
        
        $parsedInstructions = [];
        $check_order2 = [];
        $varFrame = '';
        foreach ($root->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE && $node->tagName !== 'instruction') {
                throw new \Exception('Invalid instruction name.', Errors::UNEXPECTED_XML_STRUCTURE);
            }
        }


        foreach ($instructions as $instruction) {
            $order = $instruction->getAttribute('order');
            //echo $order;
            if (!is_numeric($order)) {
                throw new \Exception('Order must be a number', Errors::UNEXPECTED_XML_STRUCTURE);
            }elseif ($order < 1) {
                throw new \Exception('Order must be a positive number', Errors::UNEXPECTED_XML_STRUCTURE);
            }
            $opcode = strtoupper($instruction->getAttribute('opcode'));

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
                    //var_dump($argValue);
                    $argValue = self::convertBasedOnType($argType, $argValue);
                    //var_dump($argValue);

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
                        $boolValue = boolval($argValue); // Преобразование строки в булево значение
                        if ($boolValue === null) {
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

    private static function convertBasedOnType($type, $value) {
        switch ($type) {
            case 'int':
                return intval($value); // Преобразование в целое число
            case 'bool':
                $lowerValue = strtolower($value);
                if ($lowerValue === 'true') {
                    return true; // Явное преобразование строки 'true' в булево true
                } elseif ($lowerValue === 'false') {
                    return false; // Явное преобразование строки 'false' в булево false
                }else {
                    throw new \Exception('Invalid bool value', Errors::UNEXPECTED_XML_STRUCTURE);
                }
            case 'string':
                return self::stringEscape($value); // Применение экранирования для строки
            default:
                return $value; // Если тип не известен, возвращаем значение без изменений
        }
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
        "MOVE" => 2,  "CREATEFRAME" => 0,  "PUSHFRAME" => 0,   "POPFRAME" => 0,   "DEFVAR" => 1,  "CALL" => 1,
        "RETURN" => 0,    "PUSHS" => 1,    "POPS" => 1,  "ADD" => 3, "SUB" => 3,    "MUL" => 3,
        "IDIV" => 3,  "LT" => 3, "GT" => 3,   "EQ" => 3,  "AND" => 3,    "OR" => 3, "NOT" => 2,
        "INT2CHAR" => 2, "STRI2INT" => 3, "READ" => 2, "WRITE" => 1, "CONCAT" => 3, "STRLEN" => 2, "GETCHAR" => 3,"SETCHAR" => 3,
        "TYPE" => 2,"LABEL" => 1,"JUMP" => 1,"JUMPIFEQ" => 3, "JUMPIFNEQ" => 3,"EXIT" => 1,"DPRINT" => 1,"BREAK" => 0
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
    public $input;
    public $output;
    public function __construct($instructions, $input, $output){
        $this->instructions = $instructions;
        $this->input = $input;
        $this->output = $output;       
     
    }

    public function run(){
       
       $this->_getLabels($this->instructions);
       $this->instructionPointer = 0;
       while ($this->instructionPointer < count($this->instructions)) {
            $this->instructions[$this->instructionPointer];
            $opcode = $this->instructions[$this->instructionPointer]['opcode'];
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
            }else if($opcode === 'CONCAT' || $opcode === 'STRLEN' || $opcode === 'GETCHAR' || $opcode === 'SETCHAR' || $opcode === 'INT2CHAR' || $opcode === 'STRI2INT'){
                $name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];

                if (isset ($this->instructions[$this->instructionPointer]['arguments'][2])) {
                    $name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
                    $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
                    $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
                    if ($type3 === 'var') {
                        $this->_check($name3, $frame3 );
                    }
    
                }
              
            
                $this->_check($name1,$frame);
                if ($type2 === 'var') {
                    $this->_check($name2, $frame2);
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
            else if($opcode === 'READ'){
                $var_name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
                $var_value = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                $var_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                
                $this->_check($var_name,$frame);
                
                if($type2 === 'type'){
                    $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
                }
                if($type2 === 'var'){
                    
                    $this->_check($var_name2,$frame2);
                    $type2 = $this->{$frame2}[$var_name2];
                    if ($type2 !== 'int' && $type2 !== 'string' && $type2 !== 'bool') {
                        throw new \Exception('Wrong type of variable', Errors::UNEXPECTED_XML_STRUCTURE);
                    }
                }
                if($type2 === 'int'){
                    $input = $this->input->readString();
                    $input = intval($input);
                    $this->{$frame}[$var_name] = $input;
                }else if($type2 === 'string'){
                    $input = $this->input->readString();
                    $this->{$frame}[$var_name] = $input;
                }else if($type2 === 'bool'){
                    $input = $this->input->readString();
                    if($input === 'true'){
                        $this->{$frame}[$var_name] = true;
                    }else{
                        $this->{$frame}[$var_name] = false;
                    }
                }
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'DPRINT') {
                $var_name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $var_value = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                $this->_check($var_name,$frame);
                if($type === 'var'){
                    if($this->{$frame}[$var_name] === null){
                        throw new \Exception('Missing value', Errors::MISSING_VALUE);
                    }else{
                        if($this->{$frame}[$var_name]
                        === 'nil'){
                            echo "nil\n";
                        }
                    }
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
            else if ($opcode === 'JUMPIFEQ' || $opcode === 'JUMPIFNEQ') {
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
               
                if ($opcode === 'JUMPIFEQ') {
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
                    
                }else if ($opcode === 'JUMPIFNEQ') {
                   
                    if(gettype($value2) === gettype($value3) || $value2 === 'nil' || $value3 === 'nil'){
                    if($value2 !== $value3){
                        $label = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                        $label_index = $this->findLabelIndex($label);
                        if ($label_index === -1) {
                            throw new \Exception('Label does not exist', Errors::SEMANTIC_CHECKS);
                        }else{
                            $this->instructionPointer = $label_index;
                        }
                    }}
                    else{
                        throw new \Exception('Values are not comparable', Errors::WRONG_OPERAND_TYPE);
                    }
                }
              
               
                
            
            }
            else if ($opcode === 'TYPE'){
                $var_name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $var_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
                $this->_check($var_name,$frame);
                if ($type2 === 'var') {
                    $this->_check($var_name2, $frame2);
                } 
                $this->_type();
                

            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'BREAK') {
                echo "Instruction pointer: " . $this->instructionPointer . "\n";
                echo "Global frame: \n";
                var_dump($this->GF);
                echo "Local frame: \n";
                var_dump($this->LF);
                echo "Temporary frame: \n";
                var_dump($this->TF);
                echo "Data stack: \n";
                var_dump($this->dataStack);
                echo "Call stack: \n";
                var_dump($this->callStack);

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
            else if($opcode === 'CALL'){
                $label = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                $label_index = $this->findLabelIndex($label);
                if ($label_index === -1) {
                    throw new \Exception('Label not found', Errors::SEMANTIC_CHECKS);
                }else{
                    array_push($this->callStack, $this->instructionPointer + 1);
                    $this->instructionPointer = $label_index;
                }
            }
            else if($opcode === 'RETURN'){
                if (empty($this->callStack)) {
                    throw new \Exception('Call stack is empty', Errors::MISSING_VALUE);
                }
                $this->instructionPointer = array_pop($this->callStack);

            }
            
            
            else if ($opcode == 'EXIT') {
                $exitCode = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                if (!is_numeric($exitCode)) {
                    throw new \Exception('Exit code must be a number', Errors::WRONG_OPERAND_TYPE);
                }
                if ($exitCode < 0 || $exitCode > 9) {
                    throw new \Exception('Exit code must be in range 0-49', Errors::WRONG_OPERANT_VALUE);
                }
                exit($exitCode);
            }
            else if($opcode === 'PUSHS'){
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $value = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                if($type === 'var'){
                    $this->_check($name,$frame);
                    $value = $this->{$frame}[$name];
                }
                array_push($this->dataStack, $value);
            }
            else if($opcode === 'POPS'){
                $var_name = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
                $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
                $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
                $this->_check($var_name,$frame);
                if (empty($this->dataStack)) {
                    throw new \Exception('Data stack is empty', Errors::MISSING_VALUE);
                }else{
                    $this->{$frame}[$var_name] = array_pop($this->dataStack);
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
                throw new \Exception('Variable already exists in frame', Errors::SEMANTIC_CHECKS);
            }
           
            $this->GF[$var_name] = null;
           // echo "frame: " . $frame . "\n";
           // var_dump($this->GF);
        } elseif ($frame === 'LF') {
            if (array_key_exists($var_name, $this->LF)) {
                throw new \Exception('Variable already exists in frame', Errors::SEMANTIC_CHECKS);
            }
            $this->LF[$var_name] = null;
            //echo "frame: " . $frame . "\n";
           // var_dump($this->{$frame});
            
        } elseif ($frame === 'TF') {
            if (array_key_exists($var_name, $this->TF)) {
                throw new \Exception('Variable already exists in frame', Errors::SEMANTIC_CHECKS);
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
            if ( $opcodeArg === 'LT' || $opcodeArg === 'GT'){
            if (gettype($value2) !== gettype($value3) || $value2 === 'nil' || $value3 === 'nil') {

                throw new \Exception("Values are not comparable", Errors::WRONG_OPERAND_TYPE);
                
            }
        }
        if ( $opcodeArg === 'EQ'){
            if ($value2 === 'nil' || $value3 === 'nil') {
                if ($value2 === 'nil' && $value3 === 'nil') {
                    $result = true;
                } else {
                    $result = false;
                }
            } else{
                if (gettype($value2) !== gettype($value3)) {

                    throw new \Exception("Values are not comparable", Errors::WRONG_OPERAND_TYPE);
                    
                }
            }
        
        }

            


  

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
        $value1 = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
       

        if(isset ($this->instructions[$this->instructionPointer]['arguments'][2])){
            $frame3 = $this->instructions[$this->instructionPointer]['arguments'][2]['frame'];
            $type3 = $this->instructions[$this->instructionPointer]['arguments'][2]['type'];
            $arg_name3 = $this->instructions[$this->instructionPointer]['arguments'][2]['name'];
            $value3 = $this->instructions[$this->instructionPointer]['arguments'][2]['value'];
            $value3 = ($type3 === 'var') ? $this->{$frame3}[$arg_name3] : $this->instructions[$this->instructionPointer]['arguments'][2]['value'];
        }

        
     
        $opcodeArg = $this->instructions[$this->instructionPointer]['opcode'];
        
        

        $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
        $value2 = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        

        
        $value2 = ($type2 === 'var') ? $this->{$frame2}[$arg_name2] : $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
       //var_dump($value2);
       //var_dump($value3);
        if ($opcodeArg === 'CONCAT') {
            if ($this->checkVariableString($value2) && $this->checkVariableString($value3)) {
                $result = $value2 . $value3;
            } else {
                throw new \Exception("Values for CONCAT operation are not strings", Errors::WRONG_OPERAND_TYPE);
            }
        }else if($opcodeArg === 'STRLEN'){
            if ($this->checkVariableString($value2)) {
                $result = strlen($value2);
            } else {
                throw new \Exception("Values for STRLEN operation are not strings", Errors::WRONG_OPERAND_TYPE);
            }
        }else if($opcodeArg === 'GETCHAR'){
            if ($this->checkVariableString($value2) && is_int($value3)) {
                if(isset($value2[$value3])){
                $result = $value2[$value3];
                }else{
                    throw new \Exception("Index is out of bounds", Errors::WRONG_STRING);
                }
            } else {
                throw new \Exception("Values for GETCHAR operation are not strings or index is out of bounds", Errors::WRONG_OPERAND_TYPE);
            }
        } else if ($opcodeArg === 'SETCHAR') {
            if ($this->checkVariableString($value1) && is_int($value2) && $this->checkVariableString($value3))
             {
                if(isset($value1[$value2])){
                $value1[$value2] = $value3;
                $result = $value1;
                }else{
                    throw new \Exception("Index is out of bounds", Errors::WRONG_STRING);
                }
            } else {
                throw new \Exception("Values for SETCHAR operation are not strings", Errors::WRONG_OPERAND_TYPE);
            }
        } else if ($opcodeArg === 'INT2CHAR') {
            if (is_int($value2)) {
                
                if ($value2 >= 0 && $value2 <= 1114111) {
                    $result = chr($value2);
                } else {
                    throw new \Exception("Invalid Unicode ordinal value", Errors::WRONG_STRING);
                }
            } else {
                throw new \Exception("Values for INT2CHAR operation are not integers", Errors::WRONG_OPERAND_TYPE);
            }
        }
         else if ($opcodeArg === 'STRI2INT') {
            if ($this->checkVariableString($value2) && is_int($value3)) {
                $result = ord($value2[$value3]);
            } else {
                throw new \Exception("Values for STRI2INT operation are not strings", Errors::WRONG_OPERAND_TYPE);
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
            
                $result = !$value2;
                //var_dump($result);
               // var_dump($value2);
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
            
        } elseif (is_bool($value)) {
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
    private function _type(){
        $arg_name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $arg_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
        $type2 = $this->instructions[$this->instructionPointer]['arguments'][1]['type'];
        $value2 = ($type2 === 'var') ? $this->{$frame2}[$arg_name2] : $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        if ($type2 === 'var') {
            $value2 = $this->{$frame2}[$arg_name2];
          
        } else {
            $value2 = $this->instructions[$this->instructionPointer]['arguments'][1]['value'];
        }
        if ($value2 === 'nil') {
            $result = 'nil';
        } elseif (is_bool($value2)) {
            $result = 'bool';
        } elseif (is_int($value2)) {
            $result = 'int';
        } elseif (is_string($value2)) {
            $result = 'string';
        } else {
            if ($value2 === NULL) {
                $result = '';
            } else {
                throw new \Exception("Wrong type of operand", Errors::WRONG_OPERAND_TYPE);
            }
            
        }
        if (!array_key_exists($arg_name1, $this->{$frame})) {
            throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
        }
        $this->{$frame}[$arg_name1] = $result;
      
      
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

}
