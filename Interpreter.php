<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{
    

    public function execute(): int
    {
       
        try {
            
            $commandFactory = new CommandFactory($this->input, $this->stdout, $this->stderr);
            $domDocument = $this->source->getDOMDocument(); 
            $parsedInstructions = XmlParser::parse($domDocument);
            $interpret = new Program($parsedInstructions, $this->input, $this->stdout, $commandFactory);
            $interpret->run();
           
            return 0;  
        } catch (\Exception $e) {
            $this->stderr->writeString("Error: " . $e->getMessage() . "\n");
            exit($e->getCode());
        }
       
    }


    
}
class XmlParser {
    /**
     * Parses the given DOMDocument to extract program instructions.
     * @param \DOMDocument $domDocument The XML document to parse.
     * @return array<int, array{
     *     order: string,
     *     opcode: string,
     *     arguments: array<int, array{
     *         type: string,
     *         value: string,
     *         frame: string|null,
     *         name: string|null
     *     }>
     * }> Returns an array of parsed instructions.
     * @throws \Exception If the XML structure is invalid or unexpected.
     */
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
        $varName = '';
        foreach ($root->childNodes as $node) {
            /** @var \DOMElement $node */
            if ($node->nodeType === XML_ELEMENT_NODE) {
                if ($node->tagName !== 'instruction') {
                    throw new \Exception('Invalid instruction name.', Errors::UNEXPECTED_XML_STRUCTURE);
                }
            }
        }


        foreach ($instructions as $instruction) {
            $order = $instruction->getAttribute('order');
            
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
                if ($child instanceof \DOMElement) {
                    $argOrder = $child->nodeName;
                    
                    if (!in_array($argOrder, ['arg1', 'arg2', 'arg3'])) {
                        
                        throw new \Exception('Invalid argument order', Errors::UNEXPECTED_XML_STRUCTURE);
                    }
                    $argNum = intval(substr($child->nodeName, 3));
    
                    $argType = $child->getAttribute('type');
                    
                    $argValue = trim($child->textContent);
                    
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

    public static function stringEscape(string $string): mixed {
        $pattern = '/\\\\(\d{3})/';
        $callback = function ($matches) {
            return chr($matches[1]);
        };
        return preg_replace_callback($pattern, $callback, $string);
    }
    /**
     * @param array<string,mixed> $a
    * @param array<string,mixed> $b
     */
    private static function sortInstructionsByOrder(array $a, array $b): int{
        return $a['order'] - $b['order'];
    }

   /**
 * @param string $type
 * @param mixed $value
 * @return mixed
 * @throws \Exception
 */
private static function convertBasedOnType(string $type, $value) {
    switch ($type) {
        case 'int':
            return intval($value); 
        case 'bool':
            $lowerValue = strtolower($value);
            if ($lowerValue === 'true') {
                return true; 
            } elseif ($lowerValue === 'false') {
                return false; 
            } else {
                throw new \Exception('Invalid bool value', Errors::UNEXPECTED_XML_STRUCTURE);
            }
        case 'string':
            return self::stringEscape($value); 
        default:
            return $value; 
    }
}







}

class Opcodes{
    /**
     * @var array<string>
     */
   public $opcode = [
    "MOVE","CREATEFRAME","PUSHFRAME","POPFRAME",
    "DEFVAR","CALL","RETURN","PUSHS","POPS","ADD","SUB","MUL","IDIV","LT","GT","EQ","AND","OR",
    "NOT","INT2CHAR","STRI2INT","READ","WRITE","CONCAT","STRLEN","GETCHAR","SETCHAR",
    "TYPE","LABEL","JUMP","JUMPIFEQ","JUMPIFNEQ","EXIT","DPRINT","BREAK"
   ]; 
    

}
class Arguments_length{
    /**
     * @var array<string, int>
     */
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











interface Command {
    /**
     * @return mixed
     */
    public function execute();
}

class DefVarCommand implements Command {
    /**
     * @var Program
     */
    private $program;
    private string $varName;
    private string $frame;

    public function __construct(Program $program, string $varName, string $frame) {
        $this->program = $program;
        $this->varName = $varName;
        $this->frame = $frame;
    }

    public function execute() {
        $frameRef = &$this->program->{$this->frame};  // Get reference to the frame array

        if (array_key_exists($this->varName, $frameRef)) {
            throw new \Exception('Variable already exists in frame', Errors::SEMANTIC_CHECKS);
        }

        if ($this->frame === 'TF' && $this->program->TF_activator === 1) {
            throw new \Exception('Temporary Frame not activated', Errors::NONEXISTENT_FRAME);
        }

        $frameRef[$this->varName] = null;  // Define variable with initial value null
        //var_dump($frameRef);
    }
}

class ArithmeticCommand implements Command {
     /**
     * @var Program
     */
    
    private $program;
    private mixed $instruction;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->instruction = $instruction;
    }

    public function execute() {

        $args = $this->instruction['arguments'];

        $name1 = $args[0]['name'];
        $name2 = $args[1]['name'];
        $name3 = $args[2]['name'];

        $frame1 = $args[0]['frame'];
        $frame2 = $args[1]['frame'];
        $frame3 = $args[2]['frame'];

        $type1 = $args[0]['type'];
        $type2 = $args[1]['type'];
        $type3 = $args[2]['type'];

        // Perform checks on the variables
        $this->program->_check($name1, $frame1);
        if ($type2 === 'var') {
            $this->program->_check($name2, $frame2);
        }
        if ($type3 === 'var') {
            $this->program->_check($name3, $frame3);
        }

        // Execute arithmetic operation
        $this->program->_arithmetic();
    }
}

class StringOperationCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private mixed $instruction;
  

    public function __construct(Program $program, mixed $instruction, ) {
        $this->program = $program;
        $this->instruction = $instruction;
       
    }

    public function execute() {
        // Extract information from instruction
        $name1 = $this->instruction['arguments'][0]['name'];
        $name2 = $this->instruction['arguments'][1]['name'];
        $frame1 = $this->instruction['arguments'][0]['frame'];
        $frame2 = $this->instruction['arguments'][1]['frame'];
        //$type1 = $this->instruction['arguments'][0]['type'];
        $type2 = $this->instruction['arguments'][1]['type'];


        
        if (isset($this->instruction['arguments'][2])) {
            $name3 = $this->instruction['arguments'][2]['name'];
            $frame3 = $this->instruction['arguments'][2]['frame'];
            $type3 = $this->instruction['arguments'][2]['type'];
            if ($type3 === 'var') {
                $this->program->_check($name3, $frame3);
            }
        }
        $this->program->_check($name1, $frame1);
        if ($type2 === 'var') {
            $this->program->_check($name2, $frame2);
        } 

        // Call the _strings method with extracted data
        $this->program->_strings();
    }
}

class LogicCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $name1;
    private string $name2;
    private ?string $name3;
    private ?string $frame1;
    private ?string $frame2;
    private ?string $frame3;
    private ?string $type2;
    private ?string $type3;
    private string $opcode;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->opcode = $instruction['opcode'];
        $this->name1 = $instruction['arguments'][0]['name'];
        $this->frame1 = $instruction['arguments'][0]['frame'];

        $this->name2 = $instruction['arguments'][1]['name'];
        $this->frame2 = $instruction['arguments'][1]['frame'];
        $this->type2 = $instruction['arguments'][1]['type'];

        if ($this->opcode !== 'NOT') {
            $this->name3 = $instruction['arguments'][2]['name'];
            $this->frame3 = $instruction['arguments'][2]['frame'];
            $this->type3 = $instruction['arguments'][2]['type'];
        } else {
            $this->name3 = null;
            $this->frame3 = null;
            $this->type3 = null;
        }

    }

    public function execute() {
        // Check existence of variables
        $this->program->_check($this->name1, $this->frame1);
        if($this->type2 === 'var'){
            $this->program->_check($this->name2, $this->frame2);
        }
        if ($this->type3 === 'var') {
            $this->program->_check($this->name3, $this->frame3);
        }

        
        $this->program->_logic();
    }
}






class ChangeVarCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private ?string $arg1Value;
    private ?string $frame;
    private mixed $arg2Value;
    private string $arg2Name;
    private string $type1;
    private string $type2;
    private string $frame2;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->arg1Value = $instruction['arguments'][0]['name'];
        $this->frame = $instruction['arguments'][0]['frame'];
        $this->arg2Value = $instruction['arguments'][1]['value'];
        $this->arg2Name = $instruction['arguments'][1]['name'];
        $this->type1 = $instruction['arguments'][0]['type'];
        $this->type2 = $instruction['arguments'][1]['type'];
        $this->frame2 = $instruction['arguments'][1]['frame'];
     
        
    }

    public function execute() {
      
        if ($this->type1 === 'var') {
            $this->program->_check($this->arg1Value, $this->frame);
        }
        if ($this->type2 === 'var') {
            $this->program->_check($this->arg2Name, $this->frame2);
            $this->arg2Value = $this->program->{$this->frame2}[$this->arg2Name];
        }
        
        switch ($this->frame) {
            case 'GF':
                $this->program->GF[$this->arg1Value] = $this->arg2Value;
                //var_dump($this->program->GF);
                break;
            case 'LF':
                $this->program->LF[$this->arg1Value] = $this->arg2Value;
                break;
            case 'TF':
                $this->program->TF[$this->arg1Value] = $this->arg2Value;
                break;
        }
    
    }
}

class WriteCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $varName;
    private string $frame;
    private string $type;
    private mixed $value;
    private mixed $outputHandler;

    public function __construct(Program $program, mixed $instruction, mixed $outputHandler) {
        $this->program = $program;
        $this->varName = $instruction['arguments'][0]['name'];
        $this->frame = $instruction['arguments'][0]['frame'];
        $this->type = $instruction['arguments'][0]['type'];
        $this->value = $instruction['arguments'][0]['value'];
        $this->outputHandler = $outputHandler;
    }

    public function execute() {
        if ($this->type === 'var') {
            $this->program->_check($this->varName, $this->frame);
            $actualValue = $this->program->{$this->frame}[$this->varName];
            $this->printValue($actualValue);
        } else {
            $this->printValue($this->value);
        }
    }

    private function printValue(mixed $value): void {
        switch (gettype($value)) {
            case "boolean":    
                $this->outputHandler->writeString($value ? "true" : "false");
                break;
            case "integer":
            case "string":
                if ($value === 'nil' || $value === 'nil@nil') {
                    $this->outputHandler->writeString("");
                } else {
                    $this->outputHandler->writeString($value);
                }
                break;
            case "NULL":
                $this->outputHandler->writeString("");
                break;    
            default:
                throw new \Exception("Unsupported type for WRITE operation", Errors::WRONG_OPERAND_TYPE);
        }
    }
}

class ReadCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $varName;
    private string $frame;
    private string $type;
    private mixed $inputHandler;

    public function __construct(Program $program, mixed $instruction, mixed $inputHandler) {
        $this->program = $program;
        $this->varName = $instruction['arguments'][0]['name'];
        $this->frame = $instruction['arguments'][0]['frame'];
        $this->type = $instruction['arguments'][1]['value']; // Assuming the type is passed directly as the second argument
        $this->inputHandler = $inputHandler;
    }

    public function execute() {
        $this->program->_check($this->varName, $this->frame);
        $input = $this->inputHandler->readString();
      
        switch ($this->type) {
            case 'int':        
                if ($input === null || $input === '') {
                    $value = 'nil@nil';     
                }else{
                    if (is_numeric($input)) {
                       
                        $value = intval($input);
                    } else {
                       
                        return 'nil@nil'; 
                    }
            }
                break;
            case 'string':
                if ($input === null || $input === '') {
                    $value = 'nil@nil';     
                }else{
                $value = $input;
                }
                break;
            case 'bool':
                if ($input === null || $input === '') {
                    $value = 'nil@nil';     
                }else{
                    if ($input === 'true' || $input === 'false') {
                        $value = strtolower($input);
                    } else {
                        return 'nil@nil'; 
                    }
                }
                break;
            default:
                throw new \Exception('Unsupported input type', Errors::UNEXPECTED_XML_STRUCTURE);
        }
    
        $this->program->{$this->frame}[$this->varName] = $value;
    }
}

class DPrintCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $varName;
    private string $frame;
    private mixed $value;
    private string $type;
    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->varName = $instruction['arguments'][0]['name'];
        $this->frame = $instruction['arguments'][0]['frame'];
        $this->value = $instruction['arguments'][0]['value'];
        $this->type = $instruction['arguments'][0]['type'];
    }

    public function execute() {
        $this->program->_check($this->varName, $this->frame);  // Ensure variable exists
        if ($this->type === 'var') {
         
        $value = $this->program->{$this->frame}[$this->varName];

        if ($value === null) {
            throw new \Exception('Missing value', Errors::MISSING_VALUE);
        }

        if ($value === 'nil') {
            echo "nil\n";
        } else {
            echo $value . "\n"; // Output the value of the variable
        }
    }
    }
}

class JumpCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $label;

    public function __construct(Program $program, string $label) {
        $this->program = $program;
        $this->label = $label;
    }

    public function execute() {
        $labelIndex = $this->program->findLabelIndex($this->label);
        if ($labelIndex === -1) {
            throw new \Exception('Label does not exist', Errors::SEMANTIC_CHECKS);
        }else{
        $this->program->instructionPointer = $labelIndex;
        } 
    }
}

class ConditionalJumpCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $name1;
    private string $name2;
    private string $name3;
    private string $frame1;
    private string $frame2;
    private string $frame3;
    private string $type1;
    private string $type2;
    private string $type3;
    private string $opcode;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->opcode = $instruction['opcode'];
        $this->name1 = $instruction['arguments'][0]['name'];
        $this->frame1 = $instruction['arguments'][0]['frame'];
        $this->type1 = $instruction['arguments'][0]['type'];
        $this->name2 = $instruction['arguments'][1]['name'];
        $this->frame2 = $instruction['arguments'][1]['frame'];
        $this->type2 = $instruction['arguments'][1]['type'];
        $this->name3 = $instruction['arguments'][2]['name'];
        $this->frame3 = $instruction['arguments'][2]['frame'];
        $this->type3 = $instruction['arguments'][2]['type'];
    }

    public function execute() {
        // Check variable existence
        $this->program->_check($this->name1, $this->frame1);
        if ($this->type2 === 'var') {
            $this->program->_check($this->name2, $this->frame2);
        }
        if ($this->type3 === 'var') {
            $this->program->_check($this->name3, $this->frame3);
        }

        $value2 = ($this->type2 === 'var') ? $this->program->{$this->frame2}[$this->name2] : $this->program->instructions[$this->program->instructionPointer]['arguments'][1]['value'];
        $value3 = ($this->type3 === 'var') ? $this->program->{$this->frame3}[$this->name3] : $this->program->instructions[$this->program->instructionPointer]['arguments'][2]['value'];

        // Perform conditional jump
        if ($this->opcode === 'JUMPIFEQ') {
            if(gettype($value2) === gettype($value3) || $value2 === 'nil' || $value3 === 'nil'){
                if($value2 === $value3){
                    $this->jumpToLabel();
                }
            }
            else{
                throw new \Exception('Values are not comparable', Errors::WRONG_OPERAND_TYPE);
            }
        } elseif ($this->opcode === 'JUMPIFNEQ') {
            if(gettype($value2) === gettype($value3) || $value2 === 'nil' || $value3 === 'nil'){
                if($value2 !== $value3){
                    $this->jumpToLabel();
                }
            }
            else{
                throw new \Exception('Values are not comparable', Errors::WRONG_OPERAND_TYPE);
            }

           
        }
    }
    private function jumpToLabel(): void
     {
        $label = $this->program->instructions[$this->program->instructionPointer]['arguments'][0]['value'];
        $labelIndex = $this->program->findLabelIndex($label);
        if ($labelIndex === -1) {
            throw new \Exception('Label does not exist', Errors::SEMANTIC_CHECKS);
        }
        $this->program->instructionPointer = $labelIndex;
    }
}


class TypeCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $varName1;
    private string $varName2;
    private string $frame1;
    private string $frame2;
    private string $type2;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->varName1 = $instruction['arguments'][0]['name'];
        $this->frame1 = $instruction['arguments'][0]['frame'];
        $this->varName2 = $instruction['arguments'][1]['name'];
        $this->frame2 = $instruction['arguments'][1]['frame'];
        $this->type2 = $instruction['arguments'][1]['type'];
    }

    public function execute() {
        $this->program->_check($this->varName1, $this->frame1);
        if ($this->type2 === 'var') {
            $this->program->_check($this->varName2, $this->frame2);
        }
        $this->program->_type();
    }
}

class BreakCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private mixed $stderrHandler;

    public function __construct(Program $program, mixed $stderrHandler) {
        $this->program = $program;
        $this->stderrHandler = $stderrHandler;
    }

    public function execute() {
        $this->stderrHandler->writeString("Instruction pointer: " . $this->program->instructionPointer . "\n");
        $this->stderrHandler->writeString("Global frame (GF): \n");
        var_dump($this->program->GF);
        $this->stderrHandler->writeString("Local frame (LF): \n");
        var_dump($this->program->LF);
        $this->stderrHandler->writeString("Temporary frame (TF): \n");
        var_dump($this->program->TF);
        $this->stderrHandler->writeString("Data stack: \n");
        var_dump($this->program->dataStack);
        $this->stderrHandler->writeString("Call stack: \n");
        var_dump($this->program->callStack);
    }
}

class CreateFrameCommand implements Command {
     /**
     * @var Program
     */
    private $program;

    public function __construct(Program $program) {
        $this->program = $program;
    }

    public function execute() {
        $this->program->TF_activator = 0;
        $this->program->TF = []; // Initialize the temporary frame as empty
    }
}




class PushFrameCommand implements Command {
     /**
     * @var Program
     */
    private $program;

    public function __construct(Program $program) {
        $this->program = $program;
    }

    public function execute() {
        if ($this->program->TF_activator !== 0) {
            throw new \Exception('Temporary frame is deactivated', Errors::NONEXISTENT_FRAME);
        }
        
        if ($this->program->TF === null) {
            throw new \Exception('Temporary frame is not defined', Errors::NONEXISTENT_FRAME);
        }
        
        $this->program->LF = array_merge($this->program->LF, $this->program->TF);
        $this->program->TF_activator = 1;  // Activate the frame status
        $this->program->TF = null;         // Clear the temporary frame
    }
}

class PopFrameCommand implements Command {
     /**
     * @var Program
     */
    private $program;

    public function __construct(Program $program) {
        $this->program = $program;
    }

    public function execute() {
        if (empty($this->program->LF)) {
            throw new \Exception('Local frame is empty, cannot pop', Errors::NONEXISTENT_FRAME);
        }

        $this->program->TF = $this->program->LF;
        $this->program->TF_activator = 0;  
    }
}


class CallCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $label;

    public function __construct(Program $program, string $label) {
        $this->program = $program;
        $this->label = $label;
    }

    public function execute() {
        $label_index = $this->program->findLabelIndex($this->label);
        if ($label_index === -1) {
            throw new \Exception('Label not found', Errors::SEMANTIC_CHECKS);
        }
        array_push($this->program->callStack, $this->program->instructionPointer + 1);
        $this->program->instructionPointer = $label_index - 1;  // -1 because it will increment after execute
    }
}


class ReturnCommand implements Command {
     /**
     * @var Program
     */
    private $program;

    public function __construct(Program $program) {
        $this->program = $program;
    }

    public function execute() {
        if (empty($this->program->callStack)) {
            throw new \Exception('Call stack is empty', Errors::MISSING_VALUE);
        }
        $this->program->instructionPointer = array_pop($this->program->callStack) - 1;  // -1 because it will increment after execute
    }
}

class ExitCommand implements Command {
    private mixed $exitCode;

    public function __construct(mixed $exitCode) {
        $this->exitCode = $exitCode;
    }

    public function execute() {
        if (!is_numeric($this->exitCode)) {
            throw new \Exception('Exit code must be a number', Errors::WRONG_OPERAND_TYPE);
        }
        if ($this->exitCode < 0 || $this->exitCode > 9) {
            throw new \Exception('Exit code must be in range 0-9', Errors::WRONG_OPERANT_VALUE);
        }
        exit($this->exitCode);
    }
}

class PushCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $name;
    private string $frame;
    private string $type;
    private mixed $value;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->name = $instruction['arguments'][0]['name'];
        $this->frame = $instruction['arguments'][0]['frame'];
        $this->type = $instruction['arguments'][0]['type'];
        $this->value = $instruction['arguments'][0]['value'];
    }

    public function execute() {
       if ($this->type === 'var'){
            $this->program->_check($this->name, $this->frame);
            $this->value = $this->program->{$this->frame}[$this->name];
       }
     
        array_push($this->program->dataStack, $this->value);
        
    }
}

class PopCommand implements Command {
     /**
     * @var Program
     */
    private $program;
    private string $name;
    private string $frame;
    private string $type;
    private mixed $value;

    public function __construct(Program $program, mixed $instruction) {
        $this->program = $program;
        $this->name = $instruction['arguments'][0]['name'];
        $this->frame = $instruction['arguments'][0]['frame'];
        $this->type = $instruction['arguments'][0]['type'];
        $this->value = $instruction['arguments'][0]['value'];
    }

    public function execute() {
        $this->program->_check($this->name, $this->frame);
        if (empty($this->program->dataStack)) {
            throw new \Exception('Data stack is empty', Errors::MISSING_VALUE);
        }
        $this->program->{$this->frame}[$this->name] = array_pop($this->program->dataStack);
    }
}




class CommandFactory {
    private mixed $inputHandler;
    private mixed $outputHandler;
    private mixed $stderrHandler;

    public function __construct(mixed $inputHandler, mixed $outputHandler, mixed $stderrHandler) {
        $this->inputHandler = $inputHandler;
        $this->outputHandler = $outputHandler;
        $this->stderrHandler = $stderrHandler;
    }
    
    public function createCommand(mixed $instruction, Program $program): ?Command {
        switch ($instruction['opcode']) {
            case 'DEFVAR':
                return new DefVarCommand($program, $instruction['arguments'][0]['name'], $instruction['arguments'][0]['frame']);
            case 'MOVE':
                return new ChangeVarCommand($program, $instruction);
            case 'ADD':
            case 'MUL':
            case 'SUB':
            case 'IDIV':
            case 'LT':
            case 'GT':
            case 'EQ':
                return new ArithmeticCommand($program, $instruction);
            case 'CONCAT':
            case 'STRLEN':
            case 'GETCHAR':
            case 'SETCHAR':
            case 'INT2CHAR':
            case 'STRI2INT':
                return new StringOperationCommand($program, $instruction);
            case 'AND':
            case 'OR':
            case 'NOT':
                return new LogicCommand($program, $instruction);
            case 'WRITE':
                return new WriteCommand($program, $instruction, $this->outputHandler);
            case 'READ':
                return new ReadCommand($program, $instruction, $this->inputHandler);
            case 'DPRINT':
                return new DPrintCommand($program, $instruction);
            case 'JUMP':
                return new JumpCommand($program, $instruction['arguments'][0]['value']);
            case 'JUMPIFEQ':
            case 'JUMPIFNEQ':
                
                return new ConditionalJumpCommand($program, $instruction);
            case 'LABEL':
                return null;
            case 'TYPE':
                return new TypeCommand($program, $instruction);
            case 'BREAK':
                return new BreakCommand($program, $this->stderrHandler);
            case 'CREATEFRAME':
                return new CreateFrameCommand($program);
            case 'PUSHFRAME':
                return new PushFrameCommand($program);
            case 'POPFRAME':
                return new PopFrameCommand($program);
            case 'CALL':
                return new CallCommand($program, $instruction['arguments'][0]['value']);
            case 'RETURN':
                return new ReturnCommand($program);
            case 'EXIT':
                return new ExitCommand($instruction['arguments'][0]['value']);
            case 'PUSHS':
                return new PushCommand($program, $instruction);
            case 'POPS':
                return new PopCommand($program, $instruction);
                
            default :
                throw new \Exception('Unknown opcode', Errors::UNEXPECTED_XML_STRUCTURE);
                
            
        
        }
    }
}


class Program{
     /**
     * @var array<int, array{
     *     order: string,
     *     opcode: string,
     *     arguments: array<int, array{
     *         type: string,
     *         value: string,
     *         frame: string|null,
     *         name: string|null
     *     }>
     * }>
     */
    public array $instructions = [];  
    public mixed $GF = [];
    public mixed $LF = [];
    public int $TF_activator = 1;
    public mixed $TF = [];
    public mixed $dataStack = [];
    public mixed $callStack = [];
    public int $instructionPointer = 0;
    public int $instructionCount = 0;
    public mixed $labels = [];
    public mixed $variables = [];
    public mixed $input;
    public mixed $output;
    public mixed $commandFactory;
    public function __construct(mixed $instructions, mixed $input, mixed $output, mixed $commandFactory){
        $this->instructions = $instructions;
        $this->input = $input;
        $this->output = $output;  
        $this->commandFactory = $commandFactory;
           
     
    }

    public function run(): void{
       
       $this->_getLabels($this->instructions);
       $this->instructionPointer = 0;
       while ($this->instructionPointer < count($this->instructions)) {
        $instruction = $this->instructions[$this->instructionPointer];
        $command = $this->commandFactory->createCommand($instruction, $this);
        
        if ($command !== null) {
            $command->execute();
        }
        $this->instructionPointer++;
    }
}



      
    public function _getLabels(mixed $instructions): void 
    {
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
    
  
    public function _check(string $varName, string $frame): void{
        
    
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
   
    
    



    public function _arithmetic(): void {
        $instruction = $this->instructions[$this->instructionPointer];
        $args = $instruction['arguments'];
        $opcode = $instruction['opcode'];
    
        // Fetch operand details
        list($value1, $frame1) = $this->getValueAndFrame($args[0]);
        list($value2, $frame2) = $this->getValueAndFrame($args[1]);
        list($value3, $frame3) = $this->getValueAndFrame($args[2]);
    
        // Type casting to integers if necessary
        if (in_array($opcode, ['ADD', 'MUL', 'SUB', 'IDIV'])) {
            $value2 = $this->ensureInteger($value2);
            $value3 = $this->ensureInteger($value3);
        }
    
        // Operation handling
        switch ($opcode) {
            case 'ADD':
                $result = $value2 + $value3;
                break;
            case 'SUB':
                $result = $value2 - $value3;
                break;
            case 'MUL':
                $result = $value2 * $value3;
                break;
            case 'IDIV':
                if ($value3 === 0) {
                    throw new \Exception("Division by zero", Errors::WRONG_OPERANT_VALUE);
                }
                $result = intdiv($value2, $value3);
                break;
            case 'LT':
            case 'GT':
            case 'EQ':
                $result = $this->compareValues($value2, $value3, $opcode);
                break;
            default:
                throw new \Exception("Unsupported opcode $opcode", Errors::UNEXPECTED_XML_STRUCTURE);
        }
    
        // Store result in the target variable
        $this->storeResult($frame1, $args[0]['name'], $result);
    }
    
    private function getValueAndFrame(mixed $arg): mixed {
        $value = $arg['type'] === 'var' ? $this->{$arg['frame']}[$arg['name']] : $arg['value'];
        return [$value, $arg['frame']];
    }
    
    private function ensureInteger(mixed $value): int {
        if (!is_numeric($value) || $value != (int)$value) {
            throw new \Exception("Values must be integers", Errors::WRONG_OPERAND_TYPE);
        }
        return (int)$value;
    }
    
    private function compareValues(mixed $value2, mixed $value3, mixed $opcode): bool {
        if (($value2 === 'nil' || $value3 === 'nil') && $opcode === 'EQ') {
            return $value2 === $value3;
        } elseif ($value2 === 'nil' || $value3 === 'nil') {
            throw new \Exception("Comparison with nil not allowed", Errors::WRONG_OPERAND_TYPE);
        } elseif (gettype($value2) !== gettype($value3)) {
            throw new \Exception("Values are not comparable", Errors::WRONG_OPERAND_TYPE);
        }
    
        switch ($opcode) {
            case 'LT': return $value2 < $value3;
            case 'GT': return $value2 > $value3;
            case 'EQ': return $value2 === $value3;
            default: throw new \Exception("Invalid comparison operation", Errors::UNEXPECTED_XML_STRUCTURE);
        }
    }
    
    private function storeResult(string $frame, string $name, mixed $result): void {
        if (!array_key_exists($name, $this->{$frame})) {
            throw new \Exception("Variable $name does not exist in frame $frame");
        }
        $this->{$frame}[$name] = $result;
    }
    

    public function findLabelIndex(string $labelName): int {
        foreach ($this->instructions as $index => $instruction) {
            if ($instruction['opcode'] === 'LABEL' && $instruction['arguments'][0]['value'] === $labelName) {
                
                return $index;
            }
           
        }
        return -1; 
    }

    public function _strings(): void{
        $arg_name1 = $this->instructions[$this->instructionPointer]['arguments'][0]['name'];
        $arg_name2 = $this->instructions[$this->instructionPointer]['arguments'][1]['name'];
        $value1 = $this->instructions[$this->instructionPointer]['arguments'][0]['value'];
        $frame = $this->instructions[$this->instructionPointer]['arguments'][0]['frame'];
        $frame2 = $this->instructions[$this->instructionPointer]['arguments'][1]['frame'];
        $value3 = null;
       

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
        $result = null;
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
                $tmp = $this->{$frame}[$arg_name1];
                $tmp[$value2] = $value3;
                $result = $tmp;
                
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
                if (isset($value2[$value3])) {  // Check if the index exists in the string
                    $result = ord($value2[$value3]);
                } else {
                    throw new \Exception("Index out of bounds", Errors::WRONG_STRING);
                }
            } else {
                throw new \Exception("Values for STRI2INT operation are not strings or index is not an integer", Errors::WRONG_OPERAND_TYPE);
            }
        }
        if (!array_key_exists($arg_name1, $this->{$frame})) {
            throw new \Exception("Variable $arg_name1 does not exist in frame $frame");
        }
        $this->{$frame}[$arg_name1] = $result;

        
    }

    public function _logic(): void{
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

    public function checkVariableString(mixed $value): bool{
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
        return false;
    }
    public function _type(): void{
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

    public function convertString(mixed $value): mixed {
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
