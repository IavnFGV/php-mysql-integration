from time import sleep

import requests

URL = "https://naukroom.pipedrive.com/api/v1/deals/collection"
PARAMS = {'api_token':"215ffd56e56bb4227a0fe87363223d8aae4ddc25",
          "limit":500}

def packCustomFields(deal):
    tempDict = {}
    for key in list(deal.keys()):
        if (len(key)>39):
            tempDict[key] = deal[key]
            del deal[key]
    deal["custom_fields"] = tempDict

def iterateOverDeals(callBack):
    repeat =True
    cursor=None
    while repeat:
        if cursor is not None:
            PARAMS['cursor'] = cursor
        r = requests.get(url = URL, params = PARAMS)
        json = r.json()
        cursor = json['additional_data']['next_cursor']
        data = json["data"];
        continue_processing = False
        for deal in data:
            packCustomFields(deal)
            continue_processing = callBack(deal)
        repeat = (cursor is not None) and (continue_processing)
        sleep(0.05)



# def printDeal(deal):
#     print(deal)


# iterateOverDeals(printDeal)