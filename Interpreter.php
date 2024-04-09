<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
        // TODO: Start your code here
        // Check \IPP\Core\AbstractInterpreter for predefined I/O objects:
        // $dom = $this->source->getDOMDocument();
        // $val = $this->input->readString();
        // $this->stdout->writeString("stdout");
        // $this->stderr->writeString("stderr");


        try {
            
            //$domDocument = $this->source->getDOMDocument();
            //$xmlContent = $domDocument->saveXML();
            //$this->stdout->writeString($xmlContent);
            $domDocument = $this->source->getDOMDocument(); 
            $parsedInstructions = XmlParser::parse($domDocument);
            foreach ($parsedInstructions as $instruction) {
                
    
                foreach ($instruction['arguments'] as  $index => $argument) {
                    $argInfo = "Instruction: Order={$instruction['order']}, Opcode={$instruction['opcode']}, Arg".($index + 1)." - Type={$argument['type']}, Value={$argument['value']}\n";                    
                    $this->stdout->writeString($argInfo);

                
                }
            }
            $interpret = new Program($parsedInstructions);
            $interpret->run();
            $this->stdout->writeString("Success\n");
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
                throw new \Exception('Opcode is not valid', Errors::UNEXPECTED_XML_STRUCTURE);
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
                    
                    $argType = $child->getAttribute('type');
                    $argValue = $child->textContent;
                    //echo $argValue;
                    if (empty($argType)) {
                        throw new \Exception('Argument type must not be empty', Errors::UNEXPECTED_XML_STRUCTURE);
                    }

                    if ($argType === 'var') {
                        list($varFrame, $varName) = explode('@', $child->nodeValue, 2); // split the string by '@' into 2 parts
                        if (!in_array($varFrame, ['GF', 'LF', 'TF'])) {
                            throw new \Exception('Invalid frame name', Errors::UNEXPECTED_XML_STRUCTURE);
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
                    }
                    $arguments[] = ['type' => $argType, 'value' => $argValue, 'frame' => $varFrame, 'name' => $varName];
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
           
            
            

            $parsedInstructions[] = ['order' => $order, 'opcode' => $opcode, 'arguments' => $arguments];
        }

        return $parsedInstructions;
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
    public $TF = [];
    public $dataStack = [];
    public $callStack = [];
    public $instructionPointer = 0;
    public $instructionCount = 0;
    public $labels = [];
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
           // echo "Instruction: " . $this->instructions[$this->instructionPointer]['opcode'] . "\n";
            if ($this->instructions[$this->instructionPointer]['opcode'] === 'DEFVAR') {
                $this->_defvar();
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'MOVE') {
                $this->_check();
                $this->_change_var();
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'ADD') {
                $this->_check();
            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'WRITE') {
                $this->_check();
                if ($this->instructions[$this->instructionPointer]['arguments'][0]['value'] !== NULL) {
                    print $this->instructions[$this->instructionPointer]['arguments'][0]['value'] . "\n";

                }

            }
            else if ($this->instructions[$this->instructionPointer]['opcode'] === 'JUMP') {
                $this->_find_label();
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
        $var = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
        echo "Var: " . $var . "\n";
        echo "Frame: " . $frame . "\n";
        if ($frame === 'GF') {
            if (array_key_exists($var, $this->GF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
           
            $this->GF[] = $var;
            echo "GF stack: " . $this->GF[0] . "\n";
        } elseif ($frame === 'LF') {
            if (array_key_exists($var, $this->LF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
            $this->LF[] = $var;
            echo "LF stack: " . $this->GF[0] . "\n";
        } elseif ($frame === 'TF') {
            if (array_key_exists($var, $this->TF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
            $this->TF[] = $var;
            echo "TF stack: " . $this->GF[0] . "\n";
        }
    }
    private function _check(){
        $var = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $value = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
        $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
       
        
        if ($type === 'var') {
        if ($frame === 'GF') {
            if (!in_array($var, $this->GF)) {
                throw new \Exception('Variable does not exist in frame', Errors::NONEXISTENT_VARIABLE);
            }
            
        } elseif ($frame === 'LF') {
            if (!in_array($var, $this->LF)) {
                throw new \Exception('Variable does not exist in frame', Errors::NONEXISTENT_VARIABLE);
            }
            
        } elseif ($frame === 'TF') {
            if (!in_array($var, $this->TF)) {
                throw new \Exception('Variable does not exist in frame', Errors::NONEXISTENT_VARIABLE);
            }
           
        }
    }
    }
    private function _find_label(){
        $label = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
        if (!array_key_exists($label, $this->labels)) {
            throw new \Exception('Label does not exist', Errors::NONEXISTENT_VARIABLE);
        }
        
        $instructionPointer2 = 0;
       //echo "Instruction count: " . count($this->instructions) . "\n";
       while ($instructionPointer2 < count($this->instructions)) {
            if ($this->instructions[$instructionPointer2]['opcode'] === 'LABEL') {
                echo "LABEL FIND: " . $this->instructions[$this->instructionPointer]['arguments'][0]['value'] . "\n";
                //$this->instructionPointer = $instructionPointer2;
                break;
            }
            
            $instructionPointer2++;
       }

        
    }
    private function _change_var(){
        $var = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $type = $this->instructions[$this->instructionPointer]['arguments'][0]['type'];
        echo "Var: " . $var . "\n";
        echo "Frame: " . $frame . "\n";
        if ($frame === 'GF') {
            if (array_key_exists($var, $this->GF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
           
            $this->GF[] = $var;
            echo "GF stack: " . $this->GF[0] . "\n";
        } elseif ($frame === 'LF') {
            if (array_key_exists($var, $this->LF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
            $this->LF[] = $var;
            echo "LF stack: " . $this->GF[0] . "\n";
        } elseif ($frame === 'TF') {
            if (array_key_exists($var, $this->TF)) {
                throw new \Exception('Variable already exists in frame', Errors::NONEXISTENT_FRAME);
            }
            $this->TF[] = $var;
            echo "TF stack: " . $this->GF[0] . "\n";
        }


    }



}
