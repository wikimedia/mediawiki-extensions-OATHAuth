CREATE TABLE /*_*/oathauth_users (
	-- User ID
	id int not null primary key,

	-- Secret key
	secret varchar(255) binary not null,

	-- Secret key used for resets
	secret_reset varchar(255) binary,

	-- List of tokens
	scratch_tokens varchar(512) binary not null,

	-- List of tokens used for resets
	scratch_tokens_reset varchar(512) binary not null,

	-- Whether the user has validated their token
	is_validated boolean not null

) /*$wgDBTableOptions*/;
