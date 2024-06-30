
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


typedef enum AddressType {
	GLOBAL,
	LOCAL
} AddressType;

typedef struct MemoryPoint {
	char* name;
	int value;
	DataType* dataType;
	AddressType addressType;
	HashMap* scope; //This scope field will be null for everything except functions
} MemoryPoint;


enum StructType {
	Generic_t,
	MemoryPoint_t,
	DataType_t
};

HashMap* types;
vec* scope;

vec* output;




MemoryPoint* search_for_label(char* label) {
	HashMap* currentScope = vec_end(scope);
	while(currentScope != NULL) {
		if(hashmap_key_exists(currentScope, label)) {
			return hashmap_get(currentScope, label);
		}
		currentScope = vec_prev(scope);
	}
	return NULL;
}

/**
 * Break down each statement from the source code and determine it's purpose
 */
void ParseStatement(char* statement) {
	int separatorCharacter = findNextStmtSeparator(statement, 0);
	char* stmtIdentifier;
	stmtIdentifier = substr(statement, 0, separatorCharacter);
	trim(stmtIdentifier);
	printf("%s\t", stmtIdentifier);
	
	HashMap* currentScope = vec_end(scope);

	if(statement[0] == '}') {
		//Close a block
		currentScope = vec_pop(scope);
		if(hashmap_key_exists(currentScope, "!returnAddr")) {
			printf("Exiting FUNC %s", ((MemoryPoint*) hashmap_get(currentScope, "!returnAddr"))->name);
		} else {
			printf("Exiting Block - %d", scope->length);
		}			
		destroy_hashmap(currentScope);
	}
	
	if(strcmp("if", stmtIdentifier) == 0) {
		HashMap* ifScope = new_hashmap();
		vec_push(scope, ifScope);
	} else if(strcmp("while", stmtIdentifier) == 0) {
		HashMap* whileScope = new_hashmap();
		vec_push(scope, whileScope);
	}


	if(hashmap_key_exists(types, stmtIdentifier)) {
		int statementDeterminer =  findNextCharFromSet(statement, "(=;", separatorCharacter);
		
		if(statement[statementDeterminer] == '(') {
			DataType* returnType = hashmap_get(types, stmtIdentifier);
			//Type is a functions
			char* funcName = substr(statement, separatorCharacter, statementDeterminer-separatorCharacter);
			trim(funcName);
			printf("New FUNC %s", funcName);



			HashMap* functionScope = new_hashmap();
			MemoryPoint* returnAddr = malloc(sizeof(MemoryPoint));
			*returnAddr = (MemoryPoint) {
				.name = malloc(strlen(funcName) + 1),
				.dataType = hashmap_get(types, "entrypoint"),
				.addressType = LOCAL,
				.value = 0
			};
			hashmap_put(functionScope, "!returnAddr", returnAddr);

			MemoryPoint* func = malloc(sizeof(MemoryPoint));
			*func = (MemoryPoint) {
				.name = returnAddr->name,
				.dataType = hashmap_get(types, "entrypoint"),
				.addressType = (scope->length > 1) ? LOCAL : GLOBAL,
				.value = 0,
				.scope = functionScope
			};
			strcpy(func->name, funcName);
			
			hashmap_put(currentScope, funcName, func);
			
			vec_push(scope, functionScope);
			free(funcName);
		}
		else if(statement[statementDeterminer] == '=' || statement[statementDeterminer] == ';') {
			DataType* dataType = hashmap_get(types, stmtIdentifier);
			char* varName = substr(statement, separatorCharacter, statementDeterminer - separatorCharacter);
			trim(varName);
			printf("New %s Var %s", scope->length > 1 ? "LOCAL" : "GLOBAL", varName);
			MemoryPoint* var = malloc(sizeof(MemoryPoint));
			*var = (MemoryPoint) {
				.name = malloc(strlen(varName) + 1),
				.dataType = dataType,
				.addressType = scope->length > 1 ? LOCAL : GLOBAL
			};
			strcpy(var->name, varName);
			hashmap_put(currentScope, varName, var);
			
			free(varName);
		}
	}

	MemoryPoint* label = search_for_label(stmtIdentifier);	
	if(label != NULL) {
		//Accessing a memory point
		int statementDeterminer =  findNextCharFromSet(statement, "(=;", 0);
		if(statement[statementDeterminer] == '(') {
			//Function Call
			printf("Call FUNC %s", label->name);
		} else if(statement[statementDeterminer] == '=') {
			//Variable Assignment
			printf("Assignment into %s", label->name);
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






int main() {
	test_all();
	
	types = InitializeTypes();
	HashMap* globalScope = new_hashmap();
	scope = new_vec();
	output = new_vec();
	vec_push(scope, globalScope);
	
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
		trim(statement);
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

	types = destroy_hashmap(types);
	free(scope);
	scope = NULL;
}

