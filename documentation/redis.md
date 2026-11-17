# redis setup notes


## Generate a new ssh key
From your local terminal run :-
```
ssh-keygen -t rsa -f ~/.ssh/gcp_redis_key -C "STEPHEN_COLLIS"
```

## Add the Public key to the VM
n.b. Add the public key to the VM in google cloud

## Start the shh tunnel/port forward
```
ssh -i ~/.ssh/gcp_redis_key -L 6379:127.0.0.1:6379 STEPHEN_COLLIS@34.147.232.155
```

## User RedisInsight to connect.