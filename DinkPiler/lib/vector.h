typedef struct vec {
	unsigned int length;
	struct vec_item* iterator;
	struct vec_item* first;
	struct vec_item* last;
} vec;

vec* new_vec();

unsigned int vec_push(vec* self, void* newElement);

void* vec_pop(vec* self);

void* vec_get(vec* self, unsigned int index);

void* vec_remove(vec* self, unsigned int index);

void* vec_next(vec* self);
void* vec_prev(vec* self);
void* vec_reset(vec* self);


void** vec_flatten(vec* iterator);


/**
 * Free every item in the linked list
 */
vec* destroy_vec(vec* self);