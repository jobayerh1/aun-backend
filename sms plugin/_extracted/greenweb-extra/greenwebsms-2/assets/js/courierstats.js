function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function checkGreenwebCourier(mobile, sleeptime) {

await sleep(sleeptime * 1000);

var mobilenew = mobile.replace(/[^0-9]/g, '');

        jQuery.ajax({
            url: greenweb_courier_stats.ajax,
            type: 'POST',
            dataType: "json",
            data: {
                action: 'greenweb_courier_stats',
                mobile: mobile
            },
            success: function (response) {
                
                var datass = JSON.parse(response);
                
        if ((datass.status == "0") || (datass.status == "1")) {             
          
 if (datass.status == "0") {           

let GWnewRow = '';
 for(let i = 0; i < datass.data.length; i++) {
    let courier_data = datass.data[i];
    // Skip steadfast courier
    if (courier_data.couriername && courier_data.couriername.toLowerCase().indexOf('steadfast') !== -1) continue;
    
GWnewRow += '<tr><td>'+courier_data.couriername+'</td><td>'+courier_data.total+'</td><td>'+courier_data.delivered+'</td><td>'+courier_data.cancelled+'</td><td>'+courier_data.successrate+'</td></tr>';
    
} 

jQuery('#'+mobilenew+'_greenweb_courier_'+sleeptime+'').replaceWith(GWnewRow);   

 } else {
                    let GWnewRow = '<tr><td>Failed to get data. Response: '+datass.message+'</td></tr>';
                 jQuery('#'+mobilenew+'_greenweb_courier_'+sleeptime+'').replaceWith(GWnewRow);   
               

               }



                } else {
                   let GWnewRow = '<tr><td>Failed to get data '+response+'</td></tr>';
                 jQuery('#'+mobilenew+'_greenweb_courier_'+sleeptime+'').replaceWith(GWnewRow);   
                }
 
                  
                
              
   
            },
            error: function (response) {
                    console.log(response);
                }
        });
   


}