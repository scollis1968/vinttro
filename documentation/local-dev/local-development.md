
# Local Deployment

## Redis
To connect to the UAT Redis you need t establish an ssh tunnel :-
```
ssh -i ~/.ssh/gcp_redis_key -L 0.0.0.0:6382:127.0.0.1:6379 STEPHEN_COLLIS@34.147.232.155
```

###

 Connection string = 
```
docker exec -i suitecrm_db mysql -usuitecrm_user -psuitecrm_password suitecrm < uat_suitecrm_dump.sql
```