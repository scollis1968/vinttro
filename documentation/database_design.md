# Database Design


## vehicle
1.    reg
1.    date_registered
1.    date_purchased
1.    date_next_servive
1.    date_last_service
1.    date_next_mot
1.    date_last_mot
1.    date_last_check
1.    service_interval_months
1.    service_interval_miles
1.    date_latest_mileage
1.    latest_milage

## vehicle_check
1.    date_check
1.    checked_by
1.    

## vehicle_issue
1.    date_raised
1.    raised_by
1.    severity
1.    date_resoled
1.    resolved_by_repair 


## vehicle_repair
A vehicle repair is work being carrierd out on to a vehicle as a result of a fault or dammage.
1.    date_raiesd
1.    status (Identifed, Sheduling, Booked, Inprogress, Complete )
1.    supplier

## vehicle_work_item
1.    description
1.    est_parts_cost
1.    est_labor_cost
1.    actual_parts_cost

## vehicle_work_invoice
1.    from_company
1.    to_compny
1

## vehicle_service
A vehicle maintenance record is a . 

## fleet

## fleet_vehicle

## insuarnce_products

## insurance_motor_policy

## insurance_motor_cover

## insurance_home_policy

## insurance_home_cover

## insurance_comercial_policy

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