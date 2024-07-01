
//Standard Library
#include <stdio.h>
#include <stdlib.h>
#include <string.h>



///My files
#include "lib/vector.h"
#include "lib/strutil.h"
#include "lib/hashmap.h"
#include "lib/testing.h"




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
	DataType_t,
	IF_t,
	WHILE_t
};

//Global Vars
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
		vec_item* currentScopeEntry = vec_pop_t(scope);
		if(hashmap_key_exists(currentScope, "!returnAddr")) {
			printf("Exiting FUNC %s", ((MemoryPoint*) hashmap_get(currentScope, "!returnAddr"))->name);
		} else if(currentScopeEntry->type == IF_t) {
			printf("Exiting If Block - %d", scope->length);
		} else if(currentScopeEntry->type == WHILE_t) {
			printf("Exiting While Block - %d", scope->length);
		}
		destroy_hashmap(currentScope);
	}
	
	if(strcmp("if", stmtIdentifier) == 0) {
		HashMap* ifScope = new_hashmap();
		vec_push_t(scope, ifScope, IF_t);
	} else if(strcmp("while", stmtIdentifier) == 0) {
		HashMap* whileScope = new_hashmap();
		vec_push_t(scope, whileScope, WHILE_t);
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
				.name = funcName,
				.dataType = hashmap_get(types, "entrypoint"),
				.addressType = (scope->length > 1) ? LOCAL : GLOBAL,
				.value = 0,
				.scope = new_hashmap() //External Scope
			};

			hashmap_put(currentScope, func->name, func);
			
			HashMap* internalScope = new_hashmap();
			MemoryPoint* returnAddr = malloc(sizeof(MemoryPoint));
			*returnAddr = (MemoryPoint) {
				.name = funcName,
				.dataType = hashmap_get(types, "entrypoint"),
				.addressType = LOCAL,
				.value = 0
			};
			hashmap_put(internalScope, "!returnAddr", returnAddr);
			vec_push(scope, internalScope);

		}
		else if(statement[statementDeterminer] == '=' || statement[statementDeterminer] == ';') {
			DataType* dataType = hashmap_get(types, stmtIdentifier);
			char* varName = substr(statement, separatorCharacter, statementDeterminer - separatorCharacter);
			trim(varName);
			printf("New %s Var %s", scope->length > 1 ? "LOCAL" : "GLOBAL", varName);
			MemoryPoint* var = malloc(sizeof(MemoryPoint));
			*var = (MemoryPoint) {
				.name = varName,
				.dataType = dataType,
				.addressType = scope->length > 1 ? LOCAL : GLOBAL
			};
			hashmap_put(currentScope, varName, var);
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

void cleanup_scope(HashMap* currScope) {
	MemoryPoint* point = hashmap_reset(currScope);
	while(point != NULL) {
		if(point->name != NULL) {
			free(point->name);
			point->name = NULL;
		}
		if(point->scope != NULL) {
			cleanup_scope(point->scope);
			point->scope = NULL;
		}
		point = hashmap_next(currScope);
	}
	destroy_hashmap(currScope);
}

void cleanup() {
	HashMap* currScope = vec_pop(scope);
	while(currScope != NULL) {
		cleanup_scope(currScope);
		currScope = vec_pop(scope);
	}

	scope = destroy_vec(scope);
	output = destroy_vec(output);
	types = destroy_hashmap(types);
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
	char* sourceCode = malloc(length);
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

	cleanup();
}

