#include "vector.h"
#include "stdlib.h"
#include "string.h"
#include <regex.h>

char* substr(char* str, int start, int offset) {
	if(start < 0) {
		start = (sizeof(str)/sizeof(str[0])) - (start*sizeof(str[0]));
	}
	if(offset < 0) {
		offset = (sizeof(str)/sizeof(str[0])) - (offset*sizeof(str[0]));
		if(start > offset) {
			return NULL;
		}
		offset = offset - start;
	}

	
	char* newString = malloc(offset + 1);
	
	for(int index = 0; index<offset; index++) {
		if(start > strlen(str)) {
			newString[offset] = 0;
			return newString;
		}
		newString[index] = str[start++];
	}
	newString[offset] = 0;
	return newString;
}

char** split(char* spliterator, char* str) {
	int prevIndex = 0;
	int spliteratorLength = strlen(spliterator);

	vec* returnList = new_vec();

	for(int i=0; i<sizeof(str)/sizeof(str[0]); i++) {
		char* chunk = substr(str, i, spliteratorLength);
		if(strcmp(chunk, spliterator) == 0) {
			int newChunkLength = i - prevIndex;
			char* newChunk = substr(str, prevIndex, newChunkLength);
			vec_push(returnList, newChunk);
			prevIndex = i + spliteratorLength;
		}
		free(chunk);
	}
	char** returnFlat = (char**)vec_flatten(returnList);
	free(returnList);
	return returnFlat;
}

int trim(char* str) {
	unsigned int start = 0;
	while(str[start] <= 32) {
		start++;
	}
	unsigned int end = strlen(str)-1;
	while(str[end] <= 32) {
		end--;
	}
	unsigned int newLength = end - start + 1;
	for(unsigned int index = 0; index < newLength; index++) {
		str[index] = str[start++];
	}
	str[newLength] = 0;
	return newLength;
}

/**
 * iterator through a string until we spot something that would mean a statement is finished
 * return the char location
 */
int findTerminatorCharacter(char* code, int startingPoint) {
	for(int i=startingPoint; i<strlen(code); i++) {
		if(code[i] == ';' || code[i] == '{' || code[i] == '}') {
			return i;
		}
	}
	return -1;
}

/**
 * iterator through a string until we spot something that would mean separate a statement
 * return the char location
 */
int findNextStmtSeparator(char* code, int startingPoint) {
	for(int i=startingPoint; i<strlen(code); i++) {
		if(code[i] == '(' || code[i] == ' ' || code[i] == '=') {
			return i;
		}
	}
	return -1;
}


/**
 * iterator through a string until we spot something that would mean a statement is finished
 * return the char location
 */
int findNextChar(char* code, char character) {
	for(int i=0; i<strlen(code); i++) {
		if(code[i] == character) {
			return i;
		}
	}
	return -1;
}

/**
 * iterator through a string until we see a character in the given second string
 * return the char index
 */
int findNextCharFromSet(char* haystack, char* needles, int startingPoint) {
	unsigned int haystackLength = strlen(haystack);
	unsigned int needleLength = strlen(needles);
	for(int i=startingPoint; i<haystackLength; i++) {
		for(int j=0; j<needleLength; j++) {
			if(haystack[i] == needles[j]) {
				return i;
			}
		}
	}
	return -1;
}

