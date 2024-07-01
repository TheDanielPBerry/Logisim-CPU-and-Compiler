
#include <stdlib.h>
#include <string.h>
#include <stdio.h>
#include <stdbool.h>

#define DEFAULT_HASH_TABLE_SIZE 2048

typedef struct HashEntry {
	unsigned long long hash;
	void* element;
	unsigned int type;
	struct HashEntry* next;
	struct HashEntry* prev;
} HashEntry;

typedef struct HashMap {
	unsigned int length;
	unsigned int genericType;
	HashEntry* iterator;
	HashEntry pool[DEFAULT_HASH_TABLE_SIZE];
	HashEntry* first;
	HashEntry* last;
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

HashMap* new_hashmap_t(unsigned int genericType) {
	HashMap* new_map = calloc(1, sizeof(HashMap));
	new_map->genericType = genericType;
	return new_map;
}

HashMap* new_hashmap() {
	return new_hashmap_t(0);
}

unsigned int hashmap_put_t(HashMap* self, char* key, void* element, unsigned int type) {
	if(self->genericType > 0 && self->genericType != type) {
		//If the types are strict and don't match, don't append
		return self->length;
	}

	long long hash = get_hash(key);
	int offset = hash % DEFAULT_HASH_TABLE_SIZE;

	while(self->pool[offset].hash != 0) {
		if(self->pool[offset].hash == hash) {
			//If there is already an entry with this hash in the set, then replace it
			self->pool[offset].element = element;
			return self->length;
		}
		offset++;
	}

	//Append a new entry into hashmap
	HashEntry* last_entry = self->last;

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

unsigned int hashmap_put(HashMap* self, char* key, void* element) {
	return hashmap_put_t(self, key, element, self->genericType);
}


HashEntry* hashmap_get_t(HashMap* self, char* key) {
	unsigned long long hash = get_hash(key);
	int offset = hash % DEFAULT_HASH_TABLE_SIZE;
	while(self->pool[offset].hash != hash) {
		if(self->pool[offset].hash == 0) {
			return NULL;
		}
		offset++;
	}
	return &self->pool[offset];
}

void* hashmap_get(HashMap* self, char* key) {
	HashEntry* search = hashmap_get_t(self, key);
	if(search != NULL) {
		return search->element;
	}
	return NULL;
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
	HashEntry* iterator = self->first;
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
