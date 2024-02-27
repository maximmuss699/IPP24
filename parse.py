import sys
import xml.etree.ElementTree as ET
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

        # First, check if the symbol is not a variable
        # If it is, return as it is syntactically correct
        # If it is not, run more checks for syntax correctness
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
                # Escape sequences are allowed, but they must be in the correct format
                if "\\" in value_type:
                    # Count the number of backslashes
                    num_of_backslashes = value_type.count("\\")
                    # Count the number of escape sequences as per the regex
                    regex_matches = re.findall(r"\\[0-9]{3}", value_type)
                    # If the number of backslashes is not equal to the number of escape sequences, there is an invalid escape sequence
                    # In this case, exit with error code 23
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



def generate_xml(tokens):
    root = ET.Element("instructions")

    for token in tokens:
        instruction = token[0]
        args = token[1:]

        elem = ET.SubElement(root, instruction)
        for arg in args:
            ET.SubElement(elem, "arg").text = arg

    tree = ET.ElementTree(root)
    tree.write(sys.stdout, encoding="unicode", xml_declaration=True)


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


for line in lines[1:]:

    # Ignore lines, which begin with comment
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
        # Check number of tokens
        Parser.check_tokens(num_of_tokens, 1)

    elif instruction in ["DEFVAR", "POPS"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_var(tokens[1])

    elif instruction in ["CALL", "LABEL", "JUMP"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_label(tokens[1])

    elif instruction in ["PUSHS", "WRITE", "EXIT", "DPRINT"]:
        Parser.check_tokens(num_of_tokens, 2)
        Parser.check_symbol(tokens[1])

    elif instruction in ["MOVE", "INT2CHAR", "STRLEN", "TYPE"]:
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
                         "NOT", "STRI2INT", "CONCAT", "GETCHAR", "SETCHAR"]:
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