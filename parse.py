import sys
import xml.etree.ElementTree as ET

def print_help():
    print("""parse.py (IPP project 2023 - part 1)
    Script of type filter reads the source code in IPPcode23 from the standard input,
    checks the lexical and syntactic correctness of the code and prints the XML representation
    of the program on the standard output.
Usage:
    python3 parse.py [-k-help]
Options:
    --help - prints this help message
Error codes:
    21 - wrong or missing header in the source code written in IPPcode23,
    22 - unknown or wrong opcode in the source code written in IPPcode23,
    23 - other lexical or syntactic error in the source code written in IPPcode23.
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
    sys.exit(1)


for line in lines[1:]:
    # Odstranění bílých znaků ze začátku a konce řádku
    line = line.strip()
    tokens = line.strip().split()
    num_of_tokens = len(tokens)
    instruction = tokens[0].upper()

    if instruction in ["CREATEFRAME", "PUSHFRAME", "POPFRAME", "RETURN", "BREAK"]:
        # Check number of tokens
        if num_of_tokens != 1:
            sys.stderr.write(f"Error: Invalid number of tokens for {instruction}!\n")
            sys.exit(22)
    elif instruction in ["DEFVAR", "POPS"]:
        if num_of_tokens != 2:
            sys.stderr.write(f"Error: Invalid number of tokens for {instruction}!\n")
            sys.exit(22)

    else:
        sys.stderr.write(f"Error: Invalid instruction {instruction}!\n")
        sys.exit(22)