## Database Design

### Storage Solution

``` mermaid
erDiagram
    CLIENT ||--o{ QUOTE_REQUEST : places
    QUOTE_REQUEST ||--|{ ADVISOR : creates
    QUOTE }|..|{ CLIENT : deviverd
    ADVISOR }|..|{ QUOTE : uses
```
``` mermaid
architecture-beta
    group api(cloud)[API]

    service www()[WebForm] in api
    service db(database)[Database] in api
    service disk1(disk)[Storage] in api
    service disk2(disk)[Storage] in api
    service server(server)[Server] in api

    db:L -- R:server
    disk1:T -- B:server
    disk2:T -- B:db
```