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
                $info = "Instruction: Order={$instruction['order']}, Opcode={$instruction['opcode']}\n";
                $this->stdout->writeString($info);
    
                foreach ($instruction['arguments'] as $argument) {
                    $argInfo = "Instruction: Type={$argument['type']}, Value={$argument['value']}\n";
                    $this->stdout->writeString($argInfo);
                }
            }
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

        foreach ($instructions as $instruction) {
            $order = $instruction->getAttribute('order');
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

            $arguments = [];

            foreach ($instruction->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $argOrder = $child->nodeName;
                    if (!in_array($argOrder, ['arg1', 'arg2', 'arg3'])) {
                        
                        throw new \Exception('Invalid argument order', Errors::UNEXPECTED_XML_STRUCTURE);
                    }
                    
                    $argType = $child->getAttribute('type');
                    $argValue = $child->textContent;
                    echo $argValue;
                    if (empty($argType)) {
                        throw new \Exception('Argument type must not be empty', Errors::UNEXPECTED_XML_STRUCTURE);
                    }

                    if ($argType === 'var') {
                        if (!preg_match('/^(LF|GF|TF)@([a-zA-Z]|[_\-\$&%\*])([a-zA-Z0-9]|[_\-\$&%\*])*$/',$argValue)) {
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
                    $arguments[] = ['type' => $argType, 'value' => $argValue];
                }
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
class Errors {
    const WRONG_XML_FORMAT = 31;
    const UNEXPECTED_XML_STRUCTURE = 32;
    const INTEGRATION_ERROR = 88;
}

