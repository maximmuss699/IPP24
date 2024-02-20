import sys
import re
import sys
from enum import Enum, auto

class TokenType(Enum):
    VAR = 0
    KEYWORD = 1
    FRAME = 2
    INT = 3
    HEADER = 4
    NIL = 5
    NEWLINE = 6
    LABEL = 7
    BOOL = 8
    STRING = 9
    TYPE = 10
    EOF = 11

class KeywordType(Enum):
    MOVE = auto()
    CREATEFRAME = auto()
    PUSHFRAME = auto()
    POPFRAME = auto()
    DEFVAR = auto()
    CALL = auto()
    RETURN = auto()
    PUSHS = auto()
    POPS = auto()
    ADD = auto()
    SUB = auto()
    MUL = auto()
    IDIV = auto()
    LT = auto()
    GT = auto()
    EQ = auto()
    AND = auto()
    OR = auto()
    NOT = auto()
    INT2CHAR = auto()
    STRI2INT = auto()
    READ = auto()
    WRITE = auto()
    STRLEN = auto()
    CONCAT = auto()
    GETCHAR = auto()
    SETCHAR = auto()
    TYPE = auto()
    LABEL = auto()
    JUMP = auto()
    JUMPIFEQ = auto()
    JUMPIFNEQ = auto()
    EXIT = auto()
    DPRINT = auto()
    BREAK = auto()


class Token:
    def __init__(self, type, value=None):
        self.tokenType = type
        self.value = value

    def getType(self):
        return self.tokenType

    def getValue(self):
        return self.value




class Parser:
    def __init__(self, file):
        self.file = file
        self.keyword_regex = re.compile(r'\b(MOVE|CREATEFRAME|PUSHFRAME|POPFRAME|DEFVAR|' \
                                        r'CALL|RETURN|PUSHS|POPS|ADD|SUB|MUL|IDIV|LT|GT|' \
                                        r'EQ|AND|OR|NOT|CONCAT|GETCHAR|SETCHAR|INT2CHAR|' \
                                        r'STRI2INT|READ|WRITE|STRLEN|TYPE|LABEL|JUMP|' \
                                        r'JUMPIFEQ|JUMPIFNEQ|EXIT|DPRINT|BREAK)\b')

        self.token_specs = [
            ("HEADER",      r'\.IPPcode24'),          # Hlavička kódu
            #("KEYWORD",     r'\b(MOVE|CREATEFRAME|PUSHFRAME|POPFRAME|DEFVAR|' \
            #                r'CALL|RETURN|PUSHS|POPS|ADD|SUB|MUL|IDIV|LT|GT|' \
            #                r'EQ|AND|OR|NOT|CONCAT|GETCHAR|SETCHAR|INT2CHAR|' \
            #                r'STRI2INT|READ|WRITE|STRLEN|TYPE|LABEL|JUMP|' \
            #                r'JUMPIFEQ|JUMPIFNEQ|EXIT|DPRINT|BREAK)\b'),
            ("VAR",         r'\b(LF|TF|GF)@([A-Za-z_\-&%*!?][A-Za-z0-9_\-&%*!?]*)\b'),
            ("INT",         r'\b(int)@(-?(?:0x[0-9A-Fa-f]+|0o[0-7]+|0[0-7]*|[1-9][0-9]*|0))\b'),
            ("BOOL",        r'\b(bool)@(true|false)\b'),
            ("STRING",      r'\b(string)@(?:[^\s#\\]|\\[0-9]{3})*'),
            ("NIL",      r'\b(nil)@(nil)*\b'),
            ("TYPE",        r'\b(int|string|bool)\b'),
            ("LABEL",       r'\b([A-Za-z_\-&%*!?][A-Za-z0-9_\-&%*!?]*)\b'),
            #("CONSTANT",    r'\b(int|bool|string|nil)@[A-Za-z0-9_\-]*'),
            ("WHITESPACE",  r'[ \t\v\f\r]+'),  # Bílé znaky, ignorovány, kromě nového řádku
            ("NEWLINE",     r'\n'),            # Nový řádek
            ("COMMENT",     r'#.*'),                  # Komentáře
            ("UNKNOWN",     r'.+'),  # Neznámé tokeny
        ]

    def compare_words(self):
        tokens = re.findall(r'\b\w+\b', self.file)
        keywords = self.keyword_regex.findall(self.file)
        print("Tokens:")
        print(tokens)
        print("Keywords:")
        print(keywords)
    def parse_tokens(self):
        tokens = []
        if not re.match(self.token_specs[0][1], self.file):
            sys.exit(21)
        # Iterate over each token specification
        for token_name, token_pattern in self.token_specs:
            # Find all matches for the pattern
            matches = re.findall(token_pattern, self.file)
            # Create token objects and add to the list
            for match in matches:
                # Depending on the token name, you might want to handle differently
                # For now, we will just append the token name and the match
                tokens.append((token_name, match))
        return tokens


# Главная функция программы
def main():

    parser = Parser(sys.stdin.read())
    parser.compare_words()
   # parsed_tokens = parser.parse_tokens()
    # Print the parsed tokens
    #for token in parsed_tokens:
        #print(token)


main()
