#include <stdbool.h>
unsigned long long get_hash(char* array);
typedef struct HashMap HashMap;
HashMap* new_hashmap();
unsigned int hashmap_put(HashMap* self, char* key, void* element);
void* hashmap_get(HashMap* self, char* key);
void* hashmap_remove(HashMap* self, char* key);
bool hashmap_key_exists(HashMap* self, char* key);

void* hashmap_next(HashMap* self);
void* hashmap_prev(HashMap* self);
void* hashmap_reset(HashMap* self);

HashMap* destroy_hashmap(HashMap* self);