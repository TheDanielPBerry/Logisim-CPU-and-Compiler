enum OptionValue {
	Empty,
	NotEmpty
};
struct Option {
	enum Option option;
	void* value;
};