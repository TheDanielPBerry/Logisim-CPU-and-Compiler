#include "stdlib.h"


//generic in a vector means whether it is loosely or strictly typed
//0 means anything is allowed 
//Any other integer means the list is strictly typed and only the generic type can go in

typedef struct vec_item {
	void* element;
	unsigned int type;
	struct vec_item* prev;
	struct vec_item* next;
} vec_item;

typedef struct vec {
	unsigned int length;
	vec_item* iterator;
	vec_item* first;
	vec_item* last;
	unsigned int genericType;
} vec;

/*
 * Construct a new vector linked list with strict types
 */
vec* new_vec_t(unsigned int genericType) {
	vec *newVector = calloc(1, sizeof(vec));
	newVector->genericType = genericType;
	newVector->length = 0;
	return newVector;
} 

/*
 * Construct a new vector linked list
 */
vec* new_vec() {
	return new_vec_t(0);
} 

/**
 * Add a new item to the end of the list
 * type can be used to identify later
 */
unsigned int vec_push_t(vec* self, void* newElement, unsigned int type) {
	if(self->genericType > 0 && self->genericType != type) {
		//If the list is strictly typed and doesn't match, do not append
		return self->length;
	}
	vec_item* newItem = malloc(sizeof(vec_item));
	newItem->element = newElement;
	newItem->type = type;
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

/**
 * Append an element to the end of the linked list
 * @return the new length of the list
 */
unsigned int vec_push(vec* self, void* newElement) {
	return vec_push_t(self, newElement, self->genericType);
}




vec_item* vec_pop_t(vec* self) {
	vec_item* lastItem = self->last;
	if(lastItem != NULL) {
		if(lastItem->prev != NULL) {
			lastItem->prev->next = NULL;
		}
		if(self->first == lastItem) {
			self->first = NULL;
		}

		self->last = lastItem->prev;
		self->length--;

	}
	return lastItem;
}

/**
 * Remove the last item from the linked list
 * @return last item in the list
 */
void* vec_pop(vec* self) {
	vec_item* lastItem = vec_pop_t(self);
	if(lastItem == NULL) {
		return NULL;
	}
	void* element = lastItem->element;
	free(lastItem);
	return element;
}


void* vec_get(vec* self, unsigned int index) {
	vec_item* iterator = self->first;
	while(index > 0) {
		if(iterator->next == NULL) {
			return NULL;
		}
		iterator = iterator->next;
		index--;
	}
	return iterator->element;
}

void* vec_get_t(vec* self, unsigned int index) {
	vec_item* iterator = self->first;
	while(index > 0) {
		if(iterator->next == NULL) {
			return NULL;
		}
		iterator = iterator->next;
		index--;
	}
	return iterator;
}


/**
 * Remove an item from the linked list and bridge the entries on either side.
 */
vec_item* vec_remove_t(vec* self, unsigned int index) {
	if(index >= self->length) {
		return NULL;
	}

	vec_item* iterator = self->first;
	while(index > 0) {
		if(iterator->next == NULL) {
			return NULL;
		}
		iterator = iterator->next;
		index--;
	}

	self->length--; //If the element exists, decrease the length

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
	return iterator;

}

/**
 * Remove an item from the linked list and bridge the entries on either side.
 */
void* vec_remove(vec* self, unsigned int index) {
	vec_item* itemToRemove = vec_remove_t(self, index);
	void* element = itemToRemove->element;
	free(itemToRemove);
	return element;
}


vec_item* vec_next_t(vec* self) {
	self->iterator = self->iterator->next;
	return self->iterator;
}

/**
 * Move through the linked list in order and return the element until null
 */
void* vec_next(vec* self) {
	vec_item* iterator = vec_next_t(self);
	if(iterator != NULL) {
		return iterator->element;
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

vec_item* vec_reset_t(vec* self) {
	self->iterator = self->first;
	return self->iterator;
}
/**
 *  Reset internal iterator to first element in the list
 *  @return 
 */
void* vec_reset(vec* self) {
	vec_item* iterator = vec_reset_t(self);
	if(iterator == NULL) {
		return NULL;
	}
	return self->iterator->element;
}


/**
 * Set internal iterator to last element in list and return element
 * If no elements in list, then return NULL
 */
void* vec_end(vec* self) {
	self->iterator = self->last;
	if(self->iterator == NULL) {
		return NULL;
	}
	return self->iterator->element;
}

/**
 * Peek the last element in the vector without affecting iterator
 */
void* vec_last(vec* self) {
	if(self->last != NULL) {
		return self->last->element;
	};
	return NULL;
}

void** vec_flatten(vec* self) {
	unsigned int length = self->length;
	void** flatArray = malloc(sizeof(self->first->element) * length);
	
	vec_item* iterator = self->first;
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
	vec_item* iterator = self->first;
	while(iterator != NULL) {
		free(iterator->element);
		iterator = iterator->next;
	}
	free(self);
	return NULL;
}
