#include <assert.h>
#include <string.h>
#include <stdbool.h>
#include <stdlib.h>
#include <stdio.h>

#include "vector.h"
#include "hashmap.h"


#define RESET   "\033[0m"
#define BLACK   "\033[30m"      /* Black */
#define RED     "\033[31m"      /* Red */
#define GREEN   "\033[32m"      /* Green */
#define YELLOW  "\033[33m"      /* Yellow */
#define BLUE    "\033[34m"      /* Blue */
#define MAGENTA "\033[35m"      /* Magenta */
#define CYAN    "\033[36m"      /* Cyan */
#define WHITE   "\033[37m"      /* White */
#define BOLDBLACK   "\033[1m\033[30m"      /* Bold Black */
#define BOLDRED     "\033[1m\033[31m"      /* Bold Red */
#define BOLDGREEN   "\033[1m\033[32m"      /* Bold Green */
#define BOLDYELLOW  "\033[1m\033[33m"      /* Bold Yellow */
#define BOLDBLUE    "\033[1m\033[34m"      /* Bold Blue */
#define BOLDMAGENTA "\033[1m\033[35m"      /* Bold Magenta */
#define BOLDCYAN    "\033[1m\033[36m"      /* Bold Cyan */
#define BOLDWHITE   "\033[1m\033[37m"      /* Bold White */

void test_hashmap() {
	char* redString = "#FF0000";
	char* redString2 = malloc(strlen(redString));
	strcpy(redString2, redString);

	char* blueString = "#0000FF";
	char* blueString2 = malloc(strlen(blueString));
	strcpy(blueString2, blueString);

	HashMap* map = new_hashmap();

	//Test put
	assert(hashmap_put(map, "red", redString2) == 1);
	
	//Test get
	assert(strcmp(redString, hashmap_get(map, "red")) == 0);

	//Test put overwrites and multiples
	assert(hashmap_put(map, "BlUe-+", blueString2) == 2);
	assert(hashmap_put(map, "red", blueString2) == 2);


	//Test get
	assert(strcmp(blueString, hashmap_get(map, "red")) == 0);
	assert(strcmp(blueString, hashmap_get(map, "BlUe-+")) == 0);

	//Test overwrite again
	assert(hashmap_put(map, "red", redString2) == 2);
	
	//Test the iterator
	assert(strcmp(hashmap_reset(map), redString) == 0);
	assert(strcmp(blueString, hashmap_next(map)) == 0);
	assert(strcmp(redString, hashmap_prev(map)) == 0);
	assert(strcmp(blueString, hashmap_next(map)) == 0);
	assert(hashmap_next(map) == NULL);
		
	//Test remove	
	hashmap_remove(map, "red"); 	
	assert(hashmap_get(map, "red") == NULL);

	//Test reset after remove
	assert(strcmp(blueString, hashmap_reset(map)) == 0);

	//Test key exists
	assert(hashmap_key_exists(map, "red") == false);
	assert(hashmap_key_exists(map, "Allora") == false);
	assert(hashmap_key_exists(map, "BlUe-+") == true);
	
	map = destroy_hashmap(map);
	assert(map == NULL);

	printf(GREEN "✓ Testing HashMap - Passed\n" RESET);
}


struct Person {
	char name[30];
	int age;
};

enum Types {
	String_t,
	Person_t	
};

void test_vec() {
	char* redStringOriginal = "#FF0000";
	char* redString = malloc(strlen(redStringOriginal));
	strcpy(redString, redStringOriginal);

	char* blueStringOriginal = "#0000FF";
	char* blueString = malloc(strlen(blueStringOriginal));
	strcpy(blueString, blueStringOriginal);

	vec* vector = new_vec();

	//Test Push
	assert(vec_push(vector, redString) == 1);
	assert(vec_push(vector, blueString) == 2);
	assert(vec_push(vector, redString) == 3);

	//Test get
	assert(strcmp(redStringOriginal, vec_get(vector, 0)) == 0);
	assert(strcmp(blueStringOriginal, vec_get(vector, 1)) == 0);
	assert(strcmp(redStringOriginal, vec_get(vector, 2)) == 0);
	
	//Test remove
	assert(strcmp(blueStringOriginal, vec_remove(vector, 1)) == 0);
	assert(vector->length == 2);
	assert(strcmp(redStringOriginal, vec_get(vector, 1)) == 0);

	//make it red red blue
	assert(vec_push(vector, blueString) == 3);

	//Test pop
	assert(strcmp(blueStringOriginal, vec_pop(vector)) == 0);
	assert(strcmp(redStringOriginal, vec_pop(vector)) == 0);
	assert(vector->length == 1);
	assert(strcmp(redStringOriginal, vec_pop(vector)) == 0);
	assert(vec_pop(vector) == NULL);

	vector = destroy_vec(vector);
	assert(vector == NULL);
	
	////Test Strictly-Typed Vectors
	vector = new_vec_t(Person_t);
	

	free(redString);
	free(blueString);
	redString = NULL;
	blueString = NULL;
	vector = destroy_vec(vector);



	printf(GREEN "✓ Testing Vector - Passed\n" RESET);

}

void test_all() {
	test_hashmap();
	test_vec();
	
	printf(GREEN "✓ All Tests Passed!\n\n\n\n" RESET);
}
