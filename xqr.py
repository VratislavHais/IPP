#!/usr/bin/env python3

#XQR:xhaisv00

import argparse
import sys
import re
import xml.dom.minidom as xml

"""
    funkce vyhodnocujici podminku v query prikazu
    @param data obsahuje data z atributu
    @param operator obsahuje operator podminky (<, >, =, CONTAINS)
    @param literal obsahuje hodnotu, se kterou porovnavame data
    @param negation obsahuje 1 nebo 0 podle toho, zda ma byt podminka negovana ci ne
    @return funkce vraci true nebo false podle podminky
"""
def condition(data, operator, literal, negation):
    #podminka CONTAINS -> pokud je literal obsazen v operator, funkce vraci true (v pripade negace false) a naopak
    if (operator == "CONTAINS"):
        if (literal.isdigit()):
            sys.stderr.write("Neplatny query prikaz\n")
            sys.exit(80)
        literal = literal.replace("\"", "")
        literal = literal.replace("\'", "")
        if negation:
            if (literal not in data):
                return True
            else:
                return False
        else:
            if (literal in data):
                return True
            else:
                return False
    if (type(literal) is str):
        literal = literal.replace("\"", "")
        literal = literal.replace("\'", "")
    try:
        data = float(data)
        literal = float(literal)
    except:
        pass
    #podminka < -> pokud jsou data < literal, tak funkce vraci true (v pripade negace false) a naopak 
    if (operator == "<"):
        if negation:
            return not data < literal
        else:
            return data < literal
    #podminka > -> pokud jsou data > literal, tak funkce vraci true (v pripade negace false) a naopak
    elif (operator == ">"):
        if negation:
            return not data > literal
        else:
            return data > literal
    #podminka = -> pokud jsou data stejna jako literal, tak funkce vraci true (v pripade negace false) a naopka
    elif (operator == "="):
        if negation:
            return not literal == data
        else:
            return literal == data



# cast zpracovani vstupnich argumentu souboru
parser = argparse.ArgumentParser()
parser.add_argument("--input", help="vstupni soubor ve formatu XML (--input=file.xml)")
parser.add_argument("--output", help="vystupni soubor ve formatu XML s vysledkem skriptu (--output=file.xml)")
parser.add_argument("--query", help="dotaz v dotazovacim jazyce (--query='dotaz'")
parser.add_argument("--qf", help="dotaz v dotazovacim jazyce obsazenym v souboru (--qf=file)")
parser.add_argument("-n", action="store_true", help="XML hlavicka na vystupu skriptu neni generovana")
parser.add_argument("--root", help="jmeno paroveho korenoveho elementu obalujici vysledky (--root=element)")

arguments = parser.parse_args()

#pokud je zadany argument --query i --qf tak skript konci chybou
if (arguments.query and arguments.qf):
    sys.stderr.write("Kombinace argumentu --query s --qf neni povolena\n")
    sys.exit(1)
#pokud neni zadan argument --query ani --qf, se bere --query="" -> neznamy prikaz
elif (not arguments.query and not arguments.qf):
    sys.stderr.write("Neznamy prikaz\n")
    sys.exit(80)
#pokud byl zadan vstupni soubor
if (arguments.input != None):
    try:
        #otevreme s kodovanim utf-8
        sys.stdin = open(arguments.input, 'r')
    except:
        sys.stderr.write("Chyba vstupniho souboru\n")
        sys.exit(2)
#pokud byl zadan vystupni soubor
if (arguments.output != None):
    try:
        #otevreme soubor pro zapis
        sys.stdout = open(arguments.output, 'w')
    except:
        sys.stderr.write("Chyba otevreni vystupniho souboru\n")
        sys.exit(3)

#pokud je zadan argument --qf, tak je potreba otevrit soubor s query prikazy a nacist jej do promenne commands
if (arguments.qf != None):
    try:
        file = open(arguments.qf, 'r')
        commands = file.read()
    except:
        sys.stderr.write("Chyba otevreni vstupniho souboru s query prikazy\n")
        sys.exit(2)
#byl zadan argument --query, takze staci hodnotu argumentu vlozit do promenne commands
else:
    commands = arguments.query

#vytvoreni seznamu pro ulozeni jednotlivych hodnot u prikazu (PRIKAZ hodnota)
dictionary = dict()
# cast zpracovani prikazu
#regex, ktery vybere jednotlive hodnoty u prikazu a ulozi je. K hodnotam lze pristupovat jako ve slovniku
pattern = re.compile("SELECT\s+(?P<select>\S+)(?:\s+LIMIT\s+(?P<limit>\d+))?\s+FROM\s*(?P<from>\S+?)?(?:\s+WHERE\s+?(?P<where_attr>\S+)(?:(?:\s+NOT)+)?(?:\s+(?P<operator>CONTAINS|<|>|=)\s+(?P<literal>\d+|\"\S*\")?)??)?\s*?$")
match = re.match(pattern, commands)

#pokud je nejaka shoda
if (match):
    #SELECT musi byt vzdy
    if (not(match.group('select'))):
        sys.stderr.write("Neznamy prikaz")
        sys.exit(80)
    #diky named groups v regexu lze k hodnotam pristupovat pres pojmenovani
    dictionary['select'] = match.group('select')
    if (match.group('from')):
        dictionary['from'] = match.group('from')
    #zjisteni poctu NOT v prikazu
    while (re.search("NOT", commands)):
        if ('not' in dictionary):
            dictionary['not'] += 1
        else:
            dictionary['not'] = 1
        commands = re.sub("NOT", "", commands, 1)
    #zda je podminka negovana (NOT) nebo ne => lichy pocet NOT = negovana, sudy pocet NOT = nenegovana
    if 'not' in dictionary:
        dictionary['not'] %= 2
    else:
        dictionary['not'] = 0
    #nasledujici ify pridavaji do slovniku nepovinne prikazy
    if (match.group('limit')):
        dictionary['limit'] = match.group('limit')
    if (match.group('where_attr')):
        dictionary['where_attr'] = match.group('where_attr')
    if (match.group('operator')):
        if (match.group('literal')):
            dictionary['operator'] = match.group('operator')
            dictionary['literal'] = match.group('literal')
        else:
            sys.stderr.write("Neplatny query prikaz\n")
            sys.exit(80)
else:
    sys.stderr.write("Chybny query prikaz\n")
    exit(80)

try:
    xml_file = xml.parse(sys.stdin)
except:
    sys.stderr.write("Chybny format vstupniho souboru\n")
    sys.exit(4)
tmp = []
useful_tags = []
# cast vyhodnocovani prikazu
# FROM (bez hodnoty)
if ('from' not in dictionary):
    pass
# FROM .attribute
elif (dictionary['from'][0] == "."):
    tmp = xml_file.getElementsByTagName("*")
    for x in tmp:
        if (x.hasAttribute(dictionary['from'][1:])):
            useful_tags = x
            #staci nam pouze prvni vyskyt
            break
# FROM element.attribute
elif ("." in dictionary['from']):
    help = dictionary['from'].split(".")
    tmp = xml_file.getElementsByTagName(help[0])
    for x in tmp:
        if (x.hasAttribute(help[1])):
            useful_tags = x
            #staci nam pouze prvni vyskyt
            break
# FROM ROOT
elif (dictionary['from'] == "ROOT"):
    useful_tags = xml_file.documentElement
# FROM element
elif ('from' in dictionary):
    tmp = xml_file.getElementsByTagName(dictionary['from'])
    #staci nam pouze prvni vyskyt
    useful_tags = tmp[0]
result = []
try:
    useful_tags = useful_tags.getElementsByTagName(dictionary['select'])
except:
    pass

#pokud byl nalezen nejaky tag odpovidajici hodnotam FROM a SELECT
if (useful_tags and ('where_attr' in dictionary)):
    #WHERE .attribute operator literal
    if (dictionary['where_attr'][0] == "."):
        #pruchod vybranymi elementy
        for x in useful_tags:
            if (x.hasAttribute(dictionary['where_attr'][1:])):
                if (condition(x.getAttribute(dictionary['where_attr'][1:]), dictionary['operator'], dictionary['literal'], dictionary['not'])):
                    result.append(x)
            #pruchod jejich podelementy
            else:
                for i in x.getElementsByTagName("*"):
                    if (i.hasAttribute(dictionary['where_attr'])):
                        if (condition(i.getAttribute(dictionary['where_attr'][1:]), dictionary['operator'], dictionary['literal'], dictionary['not'])):
                            result.append(x)
                            break;
    #WHERE element.attribute operator literal
    elif ("." in dictionary['where_attr']):
        help = dictionary['where_attr'].split(".")
        for x in useful_tags:
            if (x.tagName == help[0]):
                if (x.hasAttribute(help[1])):
                    if (condition(x.getAttribute(help[1]), dictionary['operator'], dictionary['literal'], dictionary['not'])):
                        result.append(x)
                        continue
                else:
                    for i in x.getElementsByTagName("*"):
                        if (i.hasAttribute(help[1])):
                            if (condition(i.getAttribute(help[1]), dictionary['operator'], dictionary['literal'], dictionary['not'])):
                                result.append(x)
                                break
    #WHERE element operator literal
    else:
        for x in useful_tags:
            if (x.tagName == dictionary['where_attr']):
                tmp = x.getElementsByTagName("*")
                if tmp:
                    sys.stderr.write("Chybny format vstupniho souboru\n")
                    sys.exit(4)
                else:
                    if (condition(x.childNodes[0].data, dictionary['operator'], dictionary['literal'], dictionary['not'])):
                        result.append(x)
                        continue
            else:
                for i in x.getElementsByTagName("*"):
                    if (i.tagName == dictionary['where_attr']):
                        tmp = i.getElementsByTagName("*")
                        if tmp:
                            sys.stderr.write("Chybny format vstupniho souboru\n")
                            sys.exit(4)
                        else:
                            if (condition(i.childNodes[0].data, dictionary['operator'], dictionary['literal'], dictionary['not'])):
                                result.append(x)
                                break

else:
    result = useful_tags
#pokud byl zadan LIMIT, tak orizne pole pouze na pozadovany pocet prvku
if ('limit' in dictionary and result):
    #array[:value] => vybere vsechny prvky od indexu 0 po index value-1
    result = result[:int(dictionary['limit'])]

# cast vypisu
if (not arguments.n):
    sys.stdout.write("<?xml version=\"1.0\" encoding=\"utf-8\"?>")

if (arguments.root):
    sys.stdout.write("<" + arguments.root + ">")
    for x in result:
        sys.stdout.write(x.toxml())
    sys.stdout.write("</" + arguments.root + ">")
else:
    for x in result:
        sys.stdout.write(x.toxml())

sys.stdout.write("\n")
sys.exit(0)