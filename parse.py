import sys
import xml.etree.ElementTree as ET
from xml.dom import minidom
import re

class Parser:
    # Global variables for storing regexes used in the script
    var_regex = r'\b(LF|TF|GF)@([A-Za-z_\-&%*!?][A-Za-z0-9_\-&%*!?]*)\b'
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
        self.root = ET.Element("program", language="IPPcode24")
        self.tree = ET.ElementTree(self.root)

    def prettify(elem):
        """Return a pretty-printed XML string for the Element."""
        rough_string = ET.tostring(elem,'utf-8')
        reparsed = minidom.parseString(rough_string)
        return reparsed.toprettyxml(indent="  ")

    def add_instruction(self, instruction, args):
        instr_elem = ET.SubElement(self.root, "instruction", opcode=instruction)
        arg_elem = ET.SubElement(instr_elem, f"arg{1}", type="var")
        arg_elem.text = args
    def write_argument(self, number, token, instruction):
        instr_elem = ET.SubElement(self.root, "instruction", opcode=instruction)
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
            if token in ["int", "bool", "string", "nil"]:
                token_type = "type"
                token = token
        # <label>
            else:
                token_type = "label"
                token = token

        arg_elem = ET.SubElement(instr_elem, f"arg{number}", type=token_type)
        arg_elem.text = token




    def finish_xml(self):
        print('<?xml version="1.0" encoding="UTF-8"?>')
        print(XMLWriter.prettify(self.root))








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

if not input_content.strip():
    sys.stderr.write("Error: Empty input!\n")
    sys.exit(11)

# Rozdělení vstupu na řádky
lines = input_content.split('\n')

# Kontrola prvního řádku
first_line = lines[0].strip()
if not first_line.startswith(".IPPcode24"):
    print("Chyba: Chybějící nebo neplatná hlavička '.IPPcode24' na prvním řádku.")
    sys.exit(21)


xml_writer = XMLWriter()


#root = ET.Element("program", language="IPPcode24")
#xml_str = ET.tostring(root, encoding="unicode", method="xml")
#print('<?xml version="1.0" encoding="UTF-8"?>')
#print(xml_str)


for line in lines[1:]:

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
    print(tokens)


    if instruction in ["CREATEFRAME", "PUSHFRAME", "POPFRAME", "RETURN", "BREAK"]:
        Parser.check_tokens(num_of_tokens, 1)

    elif instruction in ["DEFVAR", "POPS"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_var(tokens[1])
        xml_writer.write_argument(1, tokens[1], instruction)

    elif instruction in ["CALL", "LABEL", "JUMP"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_label(tokens[1])

    elif instruction in ["PUSHS", "WRITE", "EXIT", "DPRINT"]:
        Parser.check_tokens(num_of_tokens, 2)
        #xml_writer.add_instruction(tokens[0], ["var@GF@counter", "const@int@10"])
        #xml_writer.add_instruction(tokens[0], ["var@GF@result", "const@int@5", "const@int@3"])
        Parser.check_symbol(tokens[1])
        xml_writer.write_argument(1, tokens[1], instruction)

    elif instruction in ["MOVE", "INT2CHAR", "STRLEN", "TYPE", "NOT"]:
        Parser.check_tokens(num_of_tokens, 3)
        Parser.check_var(tokens[1])
        #print(tokens[1])
        Parser.check_symbol(tokens[2])
        #print(tokens[2])

    elif instruction in ["READ"]:
        Parser.check_tokens(num_of_tokens, 3)
        Parser.check_var(tokens[1])
        Parser.check_type(tokens[2])


    elif instruction in ["ADD", "SUB", "MUL", "IDIV", "LT", "GT", "EQ", "AND", "OR",
                         "STRI2INT", "CONCAT", "GETCHAR", "SETCHAR"]:
        Parser.check_tokens(num_of_tokens, 4)
        Parser.check_var(tokens[1])
        Parser.check_symbol(tokens[2])
        Parser.check_symbol(tokens[3])

    elif instruction in ["JUMPIFEQ", "JUMPIFNEQ"]:
        Parser.check_tokens(num_of_tokens, 4)
        Parser.check_label(tokens[1])
        Parser.check_symbol(tokens[2])
        Parser.check_symbol(tokens[3])



    else:
        sys.stderr.write(f"Error: Invalid instruction {instruction}!\n")
        sys.exit(22)

xml_writer.finish_xml()