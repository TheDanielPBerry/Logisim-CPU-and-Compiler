add_library(vector vector.c)
add_library(hashmap hashmap.c)
add_library(strutil strutil.c)
add_library(testing testing.c)

target_include_directories(
	vector
	INTERFACE
	${CMAKE_CURRENT_SOURCE_DIRECTORY}
)
target_include_directories(
	hashmap
	INTERFACE
	${CMAKE_CURRENT_SOURCE_DIRECTORY}
)
target_include_directories(
	strutil
	INTERFACE
	${CMAKE_CURRENT_SOURCE_DIRECTORY}
)
target_include_directories(
	testing
	INTERFACE
	${CMAKE_CURRENT_SOURCE_DIRECTORY}
)

