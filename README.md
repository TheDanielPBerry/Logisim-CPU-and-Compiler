## Logisim CPU - v5.circ
This project is for an 8/16 bit cpu I built in Logisim.  
It has access to a number of devices for data processing as well as I/O with the user.  
Unfortunately I have not been able to figure out floating point calculations with a 16 bit word inside the logisim system.  
You can however do fixed point math operations, but this is not included in the programming language and would have to be done in the assembler.

## Compiler - assembler.php
The compiler is written in PHP and will intake source code from the main.dink file.  
The code is compiled into machine code and written to the stdout.  

## Programming Language - main.dink
The programming language has many influences taken from C such as: pointer types, array brackets indexing, if statements, while loops, and functions.  
You can explore much of the standard library I wrote in the lib directory with functions for: linked lists, string operations, hashing, maths, memory allocation, and more.   
