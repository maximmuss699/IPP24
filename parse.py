import sys
import argparse


class Parser:
    def __init__(self, file):
        self.file =file

def help_message():
    message = """
    Nápověda k skriptu:

    Skript provádí nějakou funkcionalitu...

    Parametry:
    --help: Vypíše tuto nápovědu a ukončí skript.

    Další parametry a jejich význam...

    """
    return message


# Funkce pro načítání vstupních dat ze standardního vstupu
def nacti_vstup():
    vstupni_data = sys.stdin.read()
    return vstupni_data

# Funkce pro zpracování vstupních dat
def zpracuj_vstup(vstupni_data):
    instrukce = []
    for radek in vstupni_data:
        radek = radek.strip()  # Odstranění bílých znaků z řádku
        if not radek or radek.startswith('#'):
            continue  # Ignorování prázdných řádků a komentářů
        instrukce.append(radek.split())  # Rozdělení řádku na slova a uložení do seznamu
    return instrukce

# Funkce pro generování XML reprezentace
def generuj_xml(instrukce):
    xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
    xml += '<program language="IPPcode24">\n'
    for i, instr in enumerate(instrukce, start=1):
        xml += f'\t<instruction order="{i}" opcode="{instr[0].upper()}">\n'
        for arg_num, arg in enumerate(instr[1:], start=1):
            arg_type = determine_argument_type(arg)  # Funkce pro určení typu argumentu
            xml += f'\t\t<arg{arg_num} type="{arg_type}">{arg}</arg{arg_num}>\n'
        xml += '\t</instruction>\n'
    xml += '</program>'
    return xml

# Funkce pro určení typu argumentu
def determine_argument_type(argument):
    # Zde můžete implementovat logiku pro určení typu argumentu
    # Například, můžete kontrolovat, zda je to proměnná, konstanta, návěští, atd.
    return 'unknown'

# Hlavní funkce programu
def main():
    parser = Parser(sys.stdin.read)
    vstupni_data = nacti_vstup()
    instrukce = zpracuj_vstup(vstupni_data)
    xml = generuj_xml(instrukce)
    print(xml)  # Výstup XML reprezentace


main()
