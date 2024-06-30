
#include <stdlib.h>
#include <string.h>
#include <stdio.h>
#include <stdbool.h>

#define DEFAULT_HASH_TABLE_SIZE 2048

struct HashEntry {
	unsigned long long hash;
	void* element;
	struct HashEntry* next;
	struct HashEntry* prev;
};

typedef struct HashMap {
	unsigned int length;
	struct HashEntry* iterator;
	struct HashEntry pool[DEFAULT_HASH_TABLE_SIZE];
	struct HashEntry* first;
	struct HashEntry* last;
} HashMap;


unsigned long long get_hash(char* array) {
	unsigned long long accumulator = array[0];
	for(int i = 1; i < strlen(array); i++) {
		if(i % 2 == 0) {
			accumulator *= array[i] * array[i-1];
		} else {
			accumulator *= (array[i] + array[i+1]);
		}
	}
	return accumulator;
}


HashMap* new_hashmap() {
	HashMap* new_map = calloc(1, sizeof(HashMap));
	new_map->first = NULL;
	new_map->last = NULL;
	return new_map;
}

unsigned int hashmap_put(HashMap* self, char* key, void* element) {
	unsigned long long hash = get_hash(key);
	unsigned int offset = hash % DEFAULT_HASH_TABLE_SIZE;

	while(self->pool[offset].hash != 0) {
		if(self->pool[offset].hash == hash) {
			//If there is already an entry with this hash in the set, then replace it
			self->pool[offset].element = element;
			return self->length;
		}
		offset++;
	}

	//Append a new entry into hashmap
	struct HashEntry* last_entry = self->last;

	self->last = &self->pool[offset];
	if(last_entry == NULL) {
		self->first = &self->pool[offset];
		self->iterator = self->first;
		self->pool[offset].prev = NULL;
	} else {
		self->pool[offset].prev = last_entry;
		last_entry->next = &self->pool[offset];
	}
	self->pool[offset].hash = hash;
	self->pool[offset].element = element;
	self->pool[offset].next = NULL;

	return ++self->length;
}

void* hashmap_get(HashMap* self, char* key) {
	unsigned long long hash = get_hash(key);
	int offset = hash % DEFAULT_HASH_TABLE_SIZE;
	while(self->pool[offset].hash != hash) {
		if(self->pool[offset].hash == 0) {
			return NULL;
		}
		offset++;
	}
	return self->pool[offset].element;
}

bool hashmap_key_exists(HashMap* self, char* key) {
	unsigned long long hash = get_hash(key);
	int offset = hash % DEFAULT_HASH_TABLE_SIZE;
	while(self->pool[offset].hash != hash) {
		if(self->pool[offset].hash == 0) {
			return false;
		}
		offset++;
	}
	return true;
}

/**
 * Mark a hash in the hash entry array as unoccupied and bridge the connect 
 * between the 2 entries on either side of the linked list
 */
void* hashmap_remove(HashMap* self, char* key) {
	unsigned long long hash = get_hash(key);
	int offset = hash % DEFAULT_HASH_TABLE_SIZE;
	while(self->pool[offset].hash != hash) {
		offset++;
	}
	//Mark the space in the entry as empty
	self->pool[offset].hash = 0;

	//Join the links on either side of removed element in linked list
	if(self->pool[offset].prev != NULL) {
		self->pool[offset].prev->next = self->pool[offset].next;
	}
	if(self->pool[offset].next != NULL) {
		self->pool[offset].next->prev = self->pool[offset].prev;
	}

	//If the array is emptied out, then set first and last to null as appropriate
	if(self->last == &self->pool[offset]) {
		self->last = self->pool[offset].prev;
	}
	if(self->first == &self->pool[offset]) {
		self->first = self->pool[offset].next;
	}
	if(self->iterator == &self->pool[offset]) {
		self->iterator = self->pool[offset].next;
	}

	self->length--;
	
	return self->pool[offset].element;
}


/**
 * Move through the linked list in order and return the element until null
 */
void* hashmap_next(HashMap* self) {
	if(self->iterator != NULL) {
		self->iterator = self->iterator->next;
		if(self->iterator != NULL) {
			return self->iterator->element;
		}
	}
	return NULL;
}

/**
 * 
 */
void* hashmap_prev(HashMap* self) {
	if(self->iterator != NULL) {
		self->iterator = self->iterator->prev;
		if(self->iterator != NULL) {
			return self->iterator->element;
		}
	}
	return NULL;
}

void* hashmap_reset(HashMap* self) {
	self->iterator = self->first;
	if(self->iterator == NULL) {
		return NULL;
	}
	return self->iterator->element;
}



/**
 * Iterate through every element and free the memory of that element
 */
HashMap* destroy_hashmap(HashMap* self) {
	struct HashEntry* iterator = self->first;
	while(iterator != NULL) {
		free(iterator->element);
		iterator = iterator->next;
	}
	free(self);
	return NULL;
}


/**Testing */
bool test() {
	return true;
}
