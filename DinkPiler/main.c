
//Standard Library
#include <stdio.h>
#include <stdlib.h>
#include <string.h>



///My files
#include "lib/vector.h"
#include "lib/strutil.h"
#include "lib/hashmap.h"
#include "lib/testing.h"


char* sourceCode;


typedef struct DataType {
	char name[20];
	unsigned char size;
} DataType;

enum KeywordType {
	CONTROL,
	DATATYPE
};
struct Keyword {
	char name[20];
	enum KeywordType type;
};

typedef enum AddressType {
	GLOBAL,
	LOCAL
} AddressType;

typedef struct MemoryPoint {
	char* name;
	int val;
	DataType* dataType;
	AddressType addressType;
	HashMap* scope;
} MemoryPoint;


HashMap* types;
HashMap* keywords;
HashMap* vars;
vec* scope;

vec* output;


/**
 * 
*/
void ParseStatement(char* statement) {
	int separatorCharacter = findNextStmtSeparator(statement, 0);
	char* stmtIdentifier;
	stmtIdentifier = substr(statement, 0, separatorCharacter);
	trim(stmtIdentifier);
	printf("%s\t", stmtIdentifier);


	//Check if identifier is a reserved keyword
	if(hashmap_key_exists(keywords, stmtIdentifier)) {
		
	}
	if(hashmap_key_exists(types, stmtIdentifier)) {
		int statementDeterminer =  findNextCharFromSet(statement, "(=;", separatorCharacter);
		
		if(statement[statementDeterminer] == '(') {
			DataType* returnType = hashmap_get(types, stmtIdentifier);
			//Type is a functions
			char* funcName = substr(statement, separatorCharacter, statementDeterminer-separatorCharacter);
			trim(funcName);
			printf("New FUNC %s", funcName);
			MemoryPoint* func = malloc(sizeof(MemoryPoint));
			*func = (MemoryPoint) {
				.name = malloc(strlen(funcName) + 1),
				.dataType = hashmap_get(types, "entrypoint"),
				.addressType = (scope->length > 1) ? LOCAL : GLOBAL
			};
			strcpy(func->name, funcName);
			
			hashmap_put(vars, funcName, func);
			free(funcName);
		}
		else if(statement[statementDeterminer] == '=' || statement[statementDeterminer] == ';') {
			DataType* dataType = hashmap_get(types, stmtIdentifier);
			char* varName = substr(statement, separatorCharacter, statementDeterminer - separatorCharacter);
			trim(varName);
			printf("New Var %s", varName);
			MemoryPoint* var = malloc(sizeof(MemoryPoint));
			*var = (MemoryPoint) {
				.name = malloc(strlen(varName) + 1),
				.dataType = dataType,
				.addressType = scope->length > 1 ? LOCAL : GLOBAL
			};
			strcpy(var->name, varName);
			hashmap_put(vars, varName, var);
			
			free(varName);
		}
	}

	
	if(hashmap_key_exists(vars, stmtIdentifier)) {
		//Using a a variable
		int statementDeterminer =  findNextCharFromSet(statement, "(=;", 0);
		if(statement[statementDeterminer] == '(') {
			//Function Call
			char* funcName = substr(statement, 0, statementDeterminer);
			trim(funcName);
			printf("Call FUNC %s", ((MemoryPoint*) hashmap_get(vars, funcName))->name);
			free(funcName);
		} else if(statement[statementDeterminer] == '=') {
			char* varName = substr(statement, 0, statementDeterminer);
			trim(varName);
			printf("Assignment into %s", ((MemoryPoint*) hashmap_get(vars, varName))->name);
			free(varName);
		}
	}
	printf("\n");
	free(stmtIdentifier);
}


/**
 * 
*/
HashMap* InitializeTypes() {
	HashMap* typeSet = new_hashmap();

	struct DataType* dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "void",
		.size = 0
	};
	hashmap_put(typeSet, "void", dataType);

	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "void*",
		.size = 2
	};
	hashmap_put(typeSet, "void*", dataType);

	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "char",
		.size = 1
	};
	hashmap_put(typeSet, "char", dataType);

	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "char*",
		.size = 2
	};
	hashmap_put(typeSet, "char*", dataType);
	
	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "word",
		.size = 2
	};
	hashmap_put(typeSet, "word", dataType);
	
	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "word*",
		.size = 2
	};
	hashmap_put(typeSet, "word*", dataType);

	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "entrypoint",
		.size = 2
	};
	hashmap_put(typeSet, "entrypoint", dataType);

	return typeSet;
}


/**
 * 
*/
struct HashMap* InitializeKeywords() {
	struct HashMap* keywordSet = new_hashmap();
	
	struct Keyword* keyword = malloc(sizeof(struct Keyword));
	*keyword = (struct Keyword) {
		.name = "for",
		.type = CONTROL
	};
	hashmap_put(keywordSet, "for", keyword);

	keyword = malloc(sizeof(struct Keyword));
	*keyword = (struct Keyword) {
		.name = "if",
		.type = CONTROL
	};
	hashmap_put(keywordSet, "if", keyword);

	keyword = malloc(sizeof(struct Keyword));
	*keyword = (struct Keyword) {
		.name = "while",
		.type = CONTROL
	};
	hashmap_put(keywordSet, "while", keyword);

	return keywordSet;
}


void* destroy_hashmap_vars() {
	MemoryPoint* mp = (MemoryPoint*) hashmap_reset(vars);
	while(mp != NULL) {
		free(mp->name);
		mp = (MemoryPoint*) hashmap_next(vars);
	}
	return destroy_hashmap(vars);
}

int main() {
	test_all();
	
	types = InitializeTypes();
	keywords = InitializeKeywords();
	vars = new_hashmap();
	scope = new_vec();
	output = new_vec();
	vec_push(scope, vars);
	
	FILE *fptr;
	fptr = fopen("src.dink", "r");
	
	fseek(fptr, 0, SEEK_END);
	long length = ftell(fptr);

	fseek(fptr, 0, SEEK_SET);
	sourceCode = malloc(length);
	if(sourceCode) {
		fread(sourceCode, 1, length, fptr);
	}
	fclose(fptr);

	vec* statements = new_vec();

	int stmtTerminator = findTerminatorCharacter(sourceCode, 0) + 1;
	int lastStatement = 0;
	while(stmtTerminator > 0) {
		char* statement = substr(sourceCode, lastStatement, stmtTerminator-lastStatement);
		
		int nextSpace = findNextChar(statement, ' ');
		int nextEqualSign = findNextChar(statement, '=');
		int separatorCharacter = findNextStmtSeparator(statement, 0);
		
		
		ParseStatement(statement);


		lastStatement = stmtTerminator;
		stmtTerminator = findTerminatorCharacter(sourceCode, lastStatement) + 1;
		free(statement);
	}


	//Clean up memory on exit
	free(sourceCode);
	sourceCode = NULL;

	statements = destroy_vec(statements);
	output = destroy_vec(output);

	vars = destroy_hashmap_vars();
	keywords = destroy_hashmap(keywords);
	types = destroy_hashmap(types);
	free(scope);
	scope = NULL;
}

