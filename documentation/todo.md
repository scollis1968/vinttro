# ToDo

## Latest Ramblings:-

1. Agent status
2. SuiteCRM - visp_lead
3. SuiteCRM - visp_risk
4. OU status - open/cosed
5. Outcome / Disposition.
6. Process Market Presentation
7. IQR - Insurer Quote Request
8. Populate redis client from opportunities - Visp_lead/Vehicle/Driver
9. Mobile answer reflected on agent screen.
10. Recent call list.

### 1. Agent status
I need to allow agent to set their status so that when a call arrives we know if we should deliver the call to them. Depending on the actual status they may see no notification, a full notification, or a subtle notification. I see the agent status as being a slightly more complex object possibly containg and overriding state, but it will eventualy allow for multi tasking so could have and OnCall and OnLead collection inside. This user ststus object would be held in Redis.
```json
{
    "state"     :  "Available/Busy/Unavailable/WrapUp",
    "dtState"   :  "date and time of the last status change",
    "activeSessions" : [
        {
            "sessionID"          : "",
            "dtStart"            : "",
            "dtEnd"              : "",
            "clientGUID"         : "",
            "leadGUID"           : "",
            "opportunityGUID"    : "",
            "callGUID"           : "",
            "chatGUID"           : ""
        }
    ],
    "onOpportunity" :  "GUID",
    "onLead"        :  "GUID",
    "onCall"        :  "GUID",
    "onClient"      :  "GUID"
}
```
### Sync Active sessions,
What is the best way to sync the active sessions into Redis, I am thinking that a keepalive message could be sent form the agent-phone to all open VIPS browser session and they would respond with there seesion info either directly back into redis or to the agent-phone and then redis.??


### 2. SuiteCRM - visp_lead
Create the new module according to the spec from Gemini

### 3. SuiteCRM - visp_risk
I want to create a new risk module to hold the risk, I see this a generic object that would have the market presentation pdf and a field that holds the jason risk object, the structure of this will vary dependent on the risk type (private motor, home, Buisiness etc) this will be linked to the Opportunity.

### 4. OU state
I need to be able to set the OU state so that when a call arrives the systems knows what delivery_plan to follow. Eventualy this will need to follow a time table but for the moment I am happy just to build the API methods to set/get the OU state


### 5. Outcome / Dissposition
When and agent has ended a call they need to record the outcome and potential set the next cause of action, this could be to make a follow up call, obtain quotes from insurers, send a follow up text. I am thinking of a footer bar at the bottom of the Visp screen that display buttons for each of the common outcomes and when selected a modal popup apperas that will allow the agent to add notes and set a dialback etc.

### 6. Process Market Presentation.
When a agent has gathered all the risk information in Acturis they then produce a "MarketPresentation.pdf" I want them to be able upload this MP to StuieCRM and create a visp_risk record, at this point we would also ask the agent to select the insure/schemes that they would like to obtain quotations from, this would then create a visp_inurer_quote_request (irq) and send an email with the MP attaced.

### 7. IQR - Insurer Quote Request
These records will link to the visp_risk and will hold the status and the price returned. Eventualy this record will also be used to expose the status of opportunities given to a particulat insurere and indicate the outcome, firstly was it sold, and was it this isurers quote that was sucessful, if not why was it rejected, price or features. 

### 8. Populate redis client
The client object in Redis needs to hold all opportunities and RFQ/Risk so that when a agent goes to the client-view screen they have all know information at their fingertips.

### 9. Mobile Answer
When an agent has both wrtc and mobile delivery options, If a call is answered on the mobile then the visp screens for that same agent should reflect the call status. ideally it would also allow the visp header to actualy control the mobile call, hold/transfer/hangup etc. 

### 10. Recent Call List
The agent phone screen should show all the recent phones that would or could have been offered to the agent. the idea is that the would see all calls even if they were answered by another team member of if they were not answered.

---

## lead/Opportunity status.

The status of a opportunity is distinct from the stage, differnt opportunity type will have different stages they need to go through, a storage opportunity is different from a car insurance opprtunity and different from buisness insurance. the stages of an opportunity should form part of the closure strategy

#### Status :-
 - New
 - Active
 - Closed

#### Next Action:-
This is used to drive the work flow, it needs to hold a action type "Phone"
Activity/Events

#### Strategy
A private motor insurance strategy would 
``` json
{
"Name"          : "PC1",
"Description"   : "Privete Car Insurance Web lead",
"ProductGroup"  : "insurance",
"Product"       : "privateCar",
"focalPoint"   : "CoverStartDate",
"AssignedAgnet" : "Any",
"AssigendGroup" : "InsuranceSales",  
"Stages"      :  [
    {
        "Type" :        "Marker",
        "Description" : "Lead Recieved",
        "dtStart" :     "%%dt_leadRecieved%%",
        "dtEnd"   : ""
    },
    {
        "Type" :        "Call",
        "Description" : "Inital Contact Call",
        "dtScheduedStart"  : "0",
        "dtScheduedEnd"    : "+1",
        "dtStart" :     "dateAdd(h,%%dt_leadRecieved%%",
        "dtEnd"   :
    },
    {
        "Type" :        "Marker",
        "Description" : "Obtain Risk",
        "dtScheduedStart"  : "0",
        "dtScheduedEnd"    : "+24"
    },
    {
        "Type" :        "Marker",
        "Description" : "Create Market presentation",
        "dtScheduedStart"  : "0",
        "dtScheduedEnd"    : "+1"
    },
    {
        "Type" :        "Marker",
        "Description" : "Obtain Quotes form insurers",
        "dtScheduedStart"  : "0",
        "dtScheduedEnd"    : "+48"
    },
    {
        "Type" :        "Marker",
        "Description" : "Present Quotation to client",
        "dtScheduedStart"  : "0",
        "dtScheduedEnd"    : "+1"
    },
    {
        "Type" :        "Marker",
        "Description" : "Place on cover",
        "dtScheduedStart"  : "0",
        "dtScheduedEnd"    : "+1"
    },
]
}
```


#### Time line

We need a way of recording what stage of the process a particular lead is in this would be for instance :-
1. New
   - This is the inital state of the opportunity, before any work has been carried out. New opptotunites will closure_strategy assigned these strategies will be pre defined dependent on the lead type and source.

2. Qualify
   - Whilst this step will often be comleted naturaly as part of the next step the idea is that this is an opportunity to evaluated the viability of the opportunity/lead and reject things that we would not be able to forfill either due to price or just no suitable products. 

3. Obtain Deatils
   - This

4. Create Market presentation

5.