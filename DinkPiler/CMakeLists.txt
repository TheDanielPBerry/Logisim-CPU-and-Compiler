cmake_minimum_required(VERSION 3.16)

project(DinkPiler)
add_executable(${PROJECT_NAME} main.c)

target_link_libraries(${PROJECT_NAME} vector)
target_link_libraries(${PROJECT_NAME} hashmap)
target_link_libraries(${PROJECT_NAME} strutil)
target_link_libraries(${PROJECT_NAME} testing)

add_subdirectory(lib)
