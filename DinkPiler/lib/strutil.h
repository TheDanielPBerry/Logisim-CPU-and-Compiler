char** split(char* spliterator, char* str);
char* substr(char* str, int start, int offset);
int trim(char* str);
int findTerminatorCharacter(char* code, int startingPoint);
int findNextStmtSeparator(char* code, int startingPoint);
int findNextChar(char* code, char character);
int findNextCharFromSet(char* haystack, char* needles, int startingPoint);