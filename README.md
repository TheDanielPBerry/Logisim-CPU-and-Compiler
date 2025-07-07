## Logisim CPU - v5.circ
This project is for an 8/16 bit cpu I built in Logisim.  
It has access to a number of devices for data processing as well as I/O with the user.   
  
## Demo Video:  

[![IMAGE ALT TEXT](/output_fast.gif)]([http://www.youtube.com/watch?v=viT7sIJhgzI](https://www.youtube.com/watch?v=CdqjycKLmDs) "
16 Bit Logisim Computer with 3D Graphics")  
<http://www.youtube.com/watch?v=viT7sIJhgzI>


## Optimizations left to make:
- Write immediate mode arguments directly from memory to their destination registers instead of first into the ARG registers.  
- Quick ALU maths should be unsigned since we are never extending out the 4 bit number.  

## Compiler - assembler.php
The compiler is written in PHP and will intake source code from the main.dink file.  
The code is compiled into machine code and written to the stdout.  
It can be copied from the docker web server or just in stdout in the terminal.  

## Programming Language - main.dink
The programming language has many influences taken from C such as: pointer types, array brackets indexing, branching if statements, while loops, functions, file linking and scoped variables.  
You can explore much of the standard library I wrote in the lib directory with functions for: linked lists, string operations, hashing, maths, memory allocation, and more.   
To start the compiler server, start the docker container:
```bash
sudo docker compose up -d --build
```
<http://localhost:8080/assembler.php>
