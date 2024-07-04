
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
	FUNC_t,
	IF_t,
	WHILE_t,
	STRING_t
};

//Global Vars
HashMap* types;
vec* scope;
vec* outputData;



/**
 * Recursively search for a label up the scope list
 */
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
 * Define the bytes with a label in the assembly
 */
void InitializeGlobal(struct DataType* type, char* name, char* data, unsigned int length) {
	char* instruction = calloc(1, 512);
	strcat(instruction, name);
	strcat(instruction, ": db ");
	strcat(instruction, data);
	vec_push_t(outputData, instruction, STRING_t);
}

void ParseExpression(char* expr) {
	int separatorCharacter = findNextStmtSeparator(expr, 0);
	char* stmtIdentifier;
	stmtIdentifier = substr(expr, 0, separatorCharacter);
	trim(stmtIdentifier);
	
	MemoryPoint* label = search_for_label(stmtIdentifier);	
	if(label != NULL) {
		//Accessing a memory point
		int exprDeterminer =  findNextCharFromSet(expr, "(=;", 0);
		if(expr[exprDeterminer] == '(') {
			//Function Call
			printf("Call FUNC %s\n", label->name);
		} else if(expr[exprDeterminer] == '=') {
			//Variable Assignment
			printf("Assignment into %s\n", label->name);
		}
	}
}

/**
 * Break down each statement from the source code and determine it's purpose
 */
void ParseStatement(char* statement) {
	HashMap* currentScope = vec_end(scope);

	if(statement[0] == '}') {
		//Close a block
		vec_item* currentScopeEntry = vec_pop_t(scope);
		if(hashmap_key_exists(currentScope, "!returnAddr")) {
			printf("Exiting FUNC %s\n", ((MemoryPoint*) hashmap_get(currentScope, "!returnAddr"))->name);
		} else if(currentScopeEntry->type == IF_t) {
			printf("Exiting If Block - %d\n", scope->length);
		} else if(currentScopeEntry->type == WHILE_t) {
			printf("Exiting While Block - %d\n", scope->length);
		}
		destroy_hashmap(currentScope);
		return;
	}

	int separatorCharacter = findNextStmtSeparator(statement, 0);
	char* stmtIdentifier;
	stmtIdentifier = substr(statement, 0, separatorCharacter);
	trim(stmtIdentifier);
	printf("%s\t", stmtIdentifier);
	

	
	if(strcmp("if", stmtIdentifier) == 0) {
		HashMap* ifScope = new_hashmap();
		vec_push_t(scope, ifScope, IF_t);
		printf("Opening if block\n");
	} else if(strcmp("while", stmtIdentifier) == 0) {
		HashMap* whileScope = new_hashmap();
		vec_push_t(scope, whileScope, WHILE_t);
		printf("Opening While Block\n");
	}


	if(hashmap_key_exists(types, stmtIdentifier)) {
		int statementDeterminer =  findNextCharFromSet(statement, "(=;", separatorCharacter);
		
		if(statement[statementDeterminer] == '(') {
			DataType* returnType = hashmap_get(types, stmtIdentifier);
			//Type is a functions
			char* funcName = substr(statement, separatorCharacter, statementDeterminer-separatorCharacter);
			trim(funcName);
			printf("New FUNC %s\n", funcName);

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
			vec_push_t(scope, internalScope, FUNC_t);

		}
		else if(statement[statementDeterminer] == '=' || statement[statementDeterminer] == ';') {
			DataType* dataType = hashmap_get(types, stmtIdentifier);
			char* varName = substr(statement, separatorCharacter, statementDeterminer - separatorCharacter);
			trim(varName);
			printf("New %s Var %s\n", scope->length > 1 ? "LOCAL" : "GLOBAL", varName);
			MemoryPoint* var = malloc(sizeof(MemoryPoint));
			*var = (MemoryPoint) {
				.name = varName,
				.dataType = dataType,
				.addressType = scope->length > 1 ? LOCAL : GLOBAL
			};
			hashmap_put(currentScope, varName, var);
			
			if(scope->length == 1) {
				//Initialize global variable
				if(statement[statementDeterminer] == '=') {
					char* assignmentExpr = substr(statement, statementDeterminer+1, -1);
					trim(assignmentExpr);
					InitializeGlobal(dataType, varName, assignmentExpr, 0);
					free(assignmentExpr);
				}
			}

		}
		return;
	}
	free(stmtIdentifier);

	ParseExpression(statement);
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
		.name = "short",
		.size = 2
	};
	hashmap_put(typeSet, "short", dataType);
	
	dataType = malloc(sizeof(struct DataType));
	*dataType = (struct DataType) {
		.name = "short*",
		.size = 2
	};
	hashmap_put(typeSet, "short*", dataType);

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
	outputData = destroy_vec(outputData);
	types = destroy_hashmap(types);
}



void outputAsm() {
	printf("\n/////////////////////////////////\n");
	printf("SECTION .data\n");
	vec_item* iterator = vec_reset_t(outputData);
	while(iterator != NULL) {
		if(iterator->type == STRING_t) {
			printf("%s\n", (char*) iterator->element);
		}
		iterator = vec_next_t(outputData);
	}
}

int main() {
	test_all();
	
	types = InitializeTypes();
	HashMap* globalScope = new_hashmap();
	scope = new_vec();
	outputData = new_vec();
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

	outputAsm();

	//Clean up memory on exit
	free(sourceCode);
	sourceCode = NULL;
	statements = destroy_vec(statements);

	cleanup();
}

