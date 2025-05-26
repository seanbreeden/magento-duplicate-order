     _______                       __  __                      __               
    |       \                     |  \|  \                    |  \              
    | $$$$$$$\ __    __   ______  | $$ \$$  _______  ______  _| $$_     ______  
    | $$  | $$|  \  |  \ /      \ | $$|  \ /       \|      \|   $$ \   /      \ 
    | $$  | $$| $$  | $$|  $$$$$$\| $$| $$|  $$$$$$$ \$$$$$$\\$$$$$$  |  $$$$$$\
    | $$  | $$| $$  | $$| $$  | $$| $$| $$| $$      /      $$ | $$ __ | $$    $$
    | $$__/ $$| $$__/ $$| $$__/ $$| $$| $$| $$_____|  $$$$$$$ | $$|  \| $$$$$$$$
    | $$    $$ \$$    $$| $$    $$| $$| $$ \$$     \\$$    $$  \$$  $$ \$$     \
     \$$$$$$$   \$$$$$$ | $$$$$$$  \$$ \$$  \$$$$$$$ \$$$$$$$   \$$$$   \$$$$$$$
                        | $$                                                    
                        | $$                                                    
                         \$$                                                    
      ______                   __                            __                 
     /      \                 |  \                          |  \                
    |  $$$$$$\  ______    ____| $$  ______    ______         \$$ _______        
    | $$  | $$ /      \  /      $$ /      \  /      \       |  \|       \       
    | $$  | $$|  $$$$$$\|  $$$$$$$|  $$$$$$\|  $$$$$$\      | $$| $$$$$$$\      
    | $$  | $$| $$   \$$| $$  | $$| $$    $$| $$   \$$      | $$| $$  | $$      
    | $$__/ $$| $$      | $$__| $$| $$$$$$$$| $$            | $$| $$  | $$      
     \$$    $$| $$       \$$    $$ \$$     \| $$            | $$| $$  | $$      
      \$$$$$$  \$$        \$$$$$$$  \$$$$$$$ \$$             \$$ \$$   \$$                                                                              
     __       __                                           __                   
    |  \     /  \                                         |  \                  
    | $$\   /  $$  ______    ______    ______   _______  _| $$_     ______      
    | $$$\ /  $$$ |      \  /      \  /      \ |       \|   $$ \   /      \     
    | $$$$\  $$$$  \$$$$$$\|  $$$$$$\|  $$$$$$\| $$$$$$$\\$$$$$$  |  $$$$$$\    
    | $$\$$ $$ $$ /      $$| $$  | $$| $$    $$| $$  | $$ | $$ __ | $$  | $$    
    | $$ \$$$| $$|  $$$$$$$| $$__| $$| $$$$$$$$| $$  | $$ | $$|  \| $$__/ $$    
    | $$  \$ | $$ \$$    $$ \$$    $$ \$$     \| $$  | $$  \$$  $$ \$$    $$    
     \$$      \$$  \$$$$$$$ _\$$$$$$$  \$$$$$$$ \$$   \$$   \$$$$   \$$$$$$     
                           |  \__| $$                                           
                            \$$    $$                                           
                             \$$$$$$                                            

# Duplicate Order in Magento 2.4.x

Why would you ever need to duplicate an order in Magento? A few weeks ago I would have asked the same question. In this instance, it was caused by needed to re-send a subscription order through to an ERP after the expiration window for the order had expired at the payment provider. This script duplicates the order and assigns it a new increment ID. It does not re-authorize the card or do any other financial transactions.

## Usage: php duplicate_order.php <original_increment_id>
 
Author: Sean Breeden
E-mail: seanbreeden@gmail.com
