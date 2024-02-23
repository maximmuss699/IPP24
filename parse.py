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
    elif instruction in ["PUSHS", "WRITE", "EXIT", "DPRINT"]:
        Parser.check_tokens(num_of_tokens, 2)
    elif instruction in ["MOVE", "INT2CHAR", "STRLEN", "TYPE"]:
        Parser.check_tokens(num_of_tokens, 3)
    elif instruction in ["READ"]:
        Parser.check_tokens(num_of_tokens, 3)
    elif instruction in ["ADD", "SUB", "MUL", "IDIV", "LT", "GT", "EQ", "AND", "OR",
                         "NOT", "STRI2INT", "CONCAT", "GETCHAR", "SETCHAR", "JUMPIFEQ", "JUMPIFNEQ"]:
        Parser.check_tokens(num_of_tokens, 4)

    else:
        sys.stderr.write(f"Error: Invalid instruction {instruction}!\n")
        sys.exit(22)