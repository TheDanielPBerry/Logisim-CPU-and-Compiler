#include "stdlib.h"

typedef struct vec {
	unsigned int length;
	struct vec_item* iterator;
	struct vec_item* first;
	struct vec_item* last;
} vec;

struct vec_item {
	void* element;
	struct vec_item* prev;
	struct vec_item* next;
};

vec* new_vec() {
	vec *newVector = malloc(sizeof(vec));
	newVector->length = 0;
	newVector->first = NULL;
	newVector->last =  NULL;
	
	return newVector;
} 


unsigned int vec_push(vec* self, void* newElement) {
	struct vec_item* newItem = malloc(sizeof(struct vec_item));
	newItem->element = newElement;
	newItem->next = NULL;
	if(self->first == NULL) {
		self->first = newItem;
	} else {
		self->last->next = newItem;
		newItem->prev = self->last;
	}
	self->last = newItem;
	return ++self->length;
}


void* vec_pop(vec* self) {
	struct vec_item* lastElement = self->last;
	if(lastElement != NULL) {
		if(lastElement->prev != NULL) {
			lastElement->prev->next = NULL;
		}
		if(self->first == lastElement) {
			self->first = NULL;
		}

		self->last = lastElement->prev;
		self->length--;

		void* element = lastElement->element;
		free(lastElement);
		return element;
	}
	return NULL;
}


void* vec_get(vec* self, unsigned int index) {
	struct vec_item* iterator = self->first;
	while(index > 0) {
		if(iterator->next == NULL) {
			return NULL;
		}
		iterator = iterator->next;
		index--;
	}
	return iterator->element;
}

/**
 * Remove an item from the linked list and bridge the entries on either side.
 */
void* vec_remove(vec* self, unsigned int index) {
	struct vec_item* iterator = self->first;
	while(index > 0) {
		if(iterator->next == NULL) {
			return NULL;
		}
		iterator = iterator->next;
		index--;
	}

	self->length--;

	if(iterator->prev != NULL) {
		iterator->prev->next = iterator->next;
	}
	if(iterator->next != NULL) {
		iterator->next->prev = iterator->prev;
	}
	if(self->first == iterator) {
		self->first = NULL;
	}
	if(self->last == iterator) {
		self->last = iterator->prev;
	}
	void* element = iterator->element;
	free(iterator);
	return element;
}


/**
 * Move through the linked list in order and return the element until null
 */
void* vec_next(vec* self) {
	if(self->iterator != NULL) {
		self->iterator = self->iterator->next;
		if(self->iterator != NULL) {
			return self->iterator->element;
		}
	}
	return NULL;
}

/**
 * Go back to previous iterator, return null if at start of list
 */
void* vec_prev(vec* self) {
	if(self->iterator != NULL) {
		self->iterator = self->iterator->prev;
		if(self->iterator != NULL) {
			return self->iterator->element;
		}
	}
	return NULL;
}

/**
 *  Reset internal iterator to first element in the list
 */
void* vec_reset(vec* self) {
	self->iterator = self->first;
	if(self->iterator == NULL) {
		return NULL;
	}
	return self->iterator->element;
}


void** vec_flatten(vec* self) {
	unsigned int length = self->length;
	void** flatArray = malloc(sizeof(self->first->element) * length);
	
	struct vec_item* iterator = self->first;
	unsigned int index = 0;
	while(iterator->next != NULL) {
		flatArray[index] = iterator->element;
		index++;
		iterator = iterator->next;
	}
	return flatArray;
}

/**
 * Free every item in the linked list
 */
vec* destroy_vec(vec* self) {
	struct vec_item* iterator = self->first;
	while(iterator != NULL) {
		free(iterator->element);
		iterator = iterator->next;
	}
	free(self);
	return NULL;
}