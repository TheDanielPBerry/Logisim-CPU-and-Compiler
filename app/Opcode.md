
## Registers
0 = NUL		> NUL			//Write zeroes or to nothing  
1 = MEM		> MEM 			//Immediate Memory Address  
2 = MSP		> MSP			//Memory from stack   
3 = MIX		> MIX 			//Memory from index register  
4 = MRG		> MRG			//Memory from any two registers doing an alu op  
5 = A		> A				//General Purpose 8 bits  
6 = B		> B				//General Purpose 8 bits  
7 = C		> C				//General Purpose 16 bits  
8 = D		> D				//General Purpose 16 bits  
9 = PC		> PC			//Program Counter   
A = SP		> SP			//Stack Pointer  
B = IDX		> IDX			//Index  
C = (ALU)	> CMP			//ALU Operation  
> 	AND	= 0  
	OR 	= 1  
	XOR	= 2  
	ADD = 3  
	SUB = 4  
	MUL = 5  
	MULSIGN = 6  
	DIV = 7  
	DIVSIGN = 8  
	REM = 9  

D			//Opcode  
E			//Immediate 8 bit arg  
F			//Immediate 16 Bit args  
  
  
  
## Programming Language


FUNC sum(a: char, b: word) : LOCAL(index: char) : word

	IF a = b
		SET a = b
		
	ELSEIF a > b

	ELSE IF a <= 2

	ENDIF

	WHILE index < b
		SET arr[index] = b
		SET index = index + 1
	ENDWHILE

ENDFUNC 

//index is 00 00
//a is 00 01
//b is 00 02
//return val is 00 04
//return addr is 00 06
//FrameSize is 8

//IF Statment
MOV MSP + :a > A	//25 00 01 
MOV MSP + :b > D	//28 00 02
CMP A,B > NUL	//c0 56 10
MOV 1 > CMP		//ec 01
MOV :IF > PC	//f9 xx xx

//ELSEIF Statment
MOV MSP + :a > A	//25 00 01 
MOV MSP + :b > B	//28 00 02
MOV 2 > CMP		//ec 02
MOV :ELSEIF > PC	f9 xx xx




//ELSE Statement


DEFINE :IF
	//Assignment
	MOV MSP + :b > A	//25 00 02
	MOV A > MSP + :B	//52 00 04
	GOTO :ENDIF			//ec 00  f9 xx xx
//

DEFINE :ELSEIF

GOTO :ENDIF

DEFINE :ENDIF
