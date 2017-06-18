CREATE TABLE /*_*/election_voted (
	-- User ID
	ev_user int not null,

	-- Election Name
	ev_election varbinary(255) not null,
	PRIMARY KEY (ev_user,ev_election)
) /*$wgDBTableOptions*/; 
