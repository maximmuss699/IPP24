import sys
import xml.etree.ElementTree as ET
from xml.dom import minidom
import re

class Parser:
    # Global variables for storing regexes used in the script
    var_regex = r'\b(LF|TF|GF)@([A-Za-z_\-&%*!?\$][A-Za-z0-9_\-&%*!?%\$]*)\b'
    label_regex = r'^([a-zA-Z]|_|-|\$|&|%|\*|!|\?)([a-zA-Z0-9]|_|-|\$|&|%|\*|!|\?)*$'

    @staticmethod
    def check_tokens(number, expected):
        if number != expected:
            print(f"Error: Wrong number of arguments! Expected {expected}, got {number}.", file=sys.stderr)
            sys.exit(23)

    @staticmethod
    def check_var(token):
        if not re.match(Parser.var_regex, token):
            print(f"Error: Invalid REGEX {token}!", file=sys.stderr)
            sys.exit(23)

    def check_label(token):
        if not re.match(Parser.label_regex, token):
            sys.stderr.write(f"Error: Invalid label {token}!\n")
            sys.exit(23)

    def check_symbol(token):
        if '@' not in token:
            sys.exit(23)
        part = token.split('@')
        symbol_type = part[0]
        value_type = part[1]
        #print(part[0])
        #print(part[1])


        if not re.match(Parser.var_regex, token):
            # Check for integers
            if symbol_type == "int":
                # Integer can either be simple number, hexadecimal or octal number, and it cannot be empty
                if (value_type.isdigit() or re.match(r'^0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*$', value_type)
                        or re.match(r'^0[oO]?[0-7]+(_[0-7]+)*$', value_type) or value_type):
                    return
                else:
                    sys.stderr.write(f"Error: Invalid symbol {token}!\n")
                    sys.exit(23)

            # Check for booleans
            elif symbol_type == "bool" and value_type in ["true", "false"]:
                return

            # Check for strings
            elif symbol_type == "string":

                if "\\" in value_type:

                    num_of_backslashes = value_type.count("\\")

                    regex_matches = re.findall(r"\\[0-9]{3}", value_type)

                    if num_of_backslashes != len(regex_matches):
                        sys.stderr.write(f"Error: Invalid symbol {token}!\n")
                        sys.exit(23)
                    else:
                        return
                else:
                    return

            # Check for nil type
            elif symbol_type == "nil" and value_type == "nil":
                return

            # Symbol doesn't match any allowed syntax, exit with error code 23
            else:
                sys.stderr.write(f"Error: Invalid symbol {token}!\n")
                sys.exit(23)

        # Symbol is a variable, return correct
        else:
            return

    def check_type(token):
        allowed_types = {"int", "bool", "string", "nil"}
        if token not in allowed_types:
            sys.stderr.write(f"Error: Invalid type {token}!\n")
            sys.exit(23)


#############################

class XMLWriter:
    def __init__(self):
        self.root = ET.Element("program", language="IPPcodeXX")
        self.tree = ET.ElementTree(self.root)

    def prettify(elem):
        rough_string = ET.tostring(elem,'utf-8')
        reparsed = minidom.parseString(rough_string)
        return '\n'.join([line for line in reparsed.toprettyxml(indent="  ").split('\n')[1:]])


    def write_argument(self, number, token, instruction, order):
        instr_elem = ET.SubElement(self.root, "instruction", order=str(order), opcode=instruction)
        if "@" in token:
            part = token.split('@')
            token_type = part[0]
            if token_type in ["GF", "TF", "LF"]:
                token_type = "var"
                #arg_elem = ET.SubElement(instr_elem, f"arg{number}", type=token_type)
                #arg_elem.text = token
            elif token_type in ["int", "bool", "string", "nil"]:
                token = part[1]

        else:

            if instruction in ["LABEL", "JUMP", "CALL"]:
                token_type = "label"
                token = token
            # <label>
            else:
                token_type = "label"
                token = token
        arg_elem = ET.SubElement(instr_elem, f"arg{number}", type=token_type)
        arg_elem.text = token

    ############################################################################################################

    def start_instruction(self, instruction, order):
        self.current_instruction = ET.SubElement(self.root, "instruction", order=str(order), opcode=instruction)

    def finish_instruction(self):
        self.current_instruction = None


    def write_argument2(self, number, token, instruction):
        if "@" in token:
            part = token.split('@')
            token_type = part[0]
            if token_type in ["GF", "TF", "LF"]:
                token_type = "var"
            elif token_type in ["int", "bool", "string", "nil"]:
                token = part[1]

        else:

            if instruction in ["LABEL", "JUMP", "CALL"]:
                token_type = "label"
                token = token

            else:
                if token in ["int", "bool", "string", "nil"]:
                    token_type = "type"
                    token = token

                else:
                    token_type = "label"
                    token = token
        arg_elem = ET.SubElement(self.current_instruction, f"arg{number}", type=token_type)
        arg_elem.text = token




    def finish_xml(self):
        xml_str = '<?xml version="1.0" encoding="UTF-8"?>\n'
        xml_str += XMLWriter.prettify(self.root)
        print(xml_str, end='')






def print_help():
    print("""parse.py (IPP project 2024 - part 1)
    
    """)
    print()


# Check for the --help argument
if len(sys.argv) > 1:
    if sys.argv[1] == "--help":
        print_help()
        sys.exit(0)
    else:
        sys.stderr.write("Error: Invalid argument!\n")
        sys.exit(10)
# Čtení vstupu ze stdin
input_content = sys.stdin.read()



# Rozdělení vstupu na řádky
lines = input_content.split('\n')

first_non_empty_line_index = 0
for i, line in enumerate(lines):
    if line.strip():
        first_non_empty_line_index = i
        break

# Если весь ввод состоит из пустых строк
if first_non_empty_line_index == len(lines):
    sys.stderr.write("Error: Empty input!\n")
    sys.exit(11)

# Извлечение первой непустой строки (заголовка)
first_line = lines[first_non_empty_line_index].strip()
if not first_line.startswith(".IPPcodeXX"):
    print("Chyba: Chybějící nebo neplatná hlavička '.IPPcode24' na первой непустой строке.")
    sys.exit(21)



xml_writer = XMLWriter()
instruction_order = 0

for line in lines[first_non_empty_line_index + 1:]:

    if not line.strip():
        continue
    if line.startswith('#'):
        continue
    # Remove multiple spaces
    line = re.sub(r'\s+', ' ', line)
    # Remove comments
    line = re.sub(r"#.*", '', line)

    line = line.strip()
    tokens = line.strip().split()
    num_of_tokens = len(tokens)
    instruction = tokens[0].upper()
    #print(tokens)
    instruction_order += 1
    #print(instruction_order)

    if line in [".IPPcodeXX"]:
        sys.exit(23)

    if instruction in ["CREATEFRAME", "PUSHFRAME", "POPFRAME", "RETURN", "BREAK"]:
        Parser.check_tokens(num_of_tokens, 1)
        xml_writer.start_instruction(instruction, instruction_order)
        xml_writer.finish_instruction()

    elif instruction in ["DEFVAR", "POPS"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_var(tokens[1])
        xml_writer.write_argument(1, tokens[1], instruction, instruction_order)

    elif instruction in ["CALL", "LABEL", "JUMP"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_label(tokens[1])
        xml_writer.write_argument(1, tokens[1], instruction, instruction_order)

    elif instruction in ["PUSHS", "WRITE", "EXIT", "DPRINT"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_symbol(tokens[1])
        xml_writer.write_argument(1, tokens[1], instruction, instruction_order)

    elif instruction in ["MOVE", "INT2CHAR", "STRLEN", "TYPE", "NOT"]:
        Parser.check_tokens(num_of_tokens, 3)
        Parser.check_var(tokens[1])
        Parser.check_symbol(tokens[2])
        xml_writer.start_instruction(instruction, instruction_order)
        xml_writer.write_argument2(1, tokens[1], instruction)
        xml_writer.write_argument2(2, tokens[2], instruction)
        xml_writer.finish_instruction()

    elif instruction in ["READ"]:
        Parser.check_tokens(num_of_tokens, 3)
        Parser.check_var(tokens[1])
        Parser.check_type(tokens[2])
        xml_writer.start_instruction(instruction, instruction_order)
        xml_writer.write_argument2(1, tokens[1], instruction)
        xml_writer.write_argument2(2, tokens[2], instruction)
        xml_writer.finish_instruction()


    elif instruction in ["ADD", "SUB", "MUL", "IDIV", "LT", "GT", "EQ", "AND", "OR",
                         "STRI2INT", "CONCAT", "GETCHAR", "SETCHAR"]:
        Parser.check_tokens(num_of_tokens, 4)
        Parser.check_var(tokens[1])
        Parser.check_symbol(tokens[2])
        Parser.check_symbol(tokens[3])
        xml_writer.start_instruction(instruction, instruction_order)
        xml_writer.write_argument2(1, tokens[1], instruction)
        xml_writer.write_argument2(2, tokens[2], instruction)
        xml_writer.write_argument2(3, tokens[3], instruction)
        xml_writer.finish_instruction()


    elif instruction in ["JUMPIFEQ", "JUMPIFNEQ"]:
        Parser.check_tokens(num_of_tokens, 4)
        Parser.check_label(tokens[1])
        Parser.check_symbol(tokens[2])
        Parser.check_symbol(tokens[3])
        xml_writer.start_instruction(instruction, instruction_order)
        xml_writer.write_argument2(1, tokens[1], instruction)
        xml_writer.write_argument2(2, tokens[2], instruction)
        xml_writer.write_argument2(3, tokens[3], instruction)
        xml_writer.finish_instruction()




    else:
        sys.stderr.write(f"Error: Invalid instruction {instruction}!\n")
        sys.exit(22)

xml_writer.finish_xml()