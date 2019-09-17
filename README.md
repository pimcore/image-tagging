# Automatic Image-Tagging

This bundle enables automatic Image Tagging with ML technology directly in Pimcore. 
After training a model with training data, it is possible to auto-tag new images automatically. 

![Automatic Image Tagging in Pimcore](./docs/sample.gif)

- Starting auto-tagging of new assets directly in Pimcore Backend
- Machine Learning based on tensorflow
- Training models via console commands 


## Usage: 

- Preparing test data by assigning tags to asset images. 
  - Requirements for tag names: 
     - unique
     - lower case

- Training (and retraining) models via console command, e.g.
  
  ``` bin/console pimcore:tensorflow train -t country -N cars_country -m 0  ```
  
- Start auto tagging directly via Pimcore Backend or via console command, e.g.
 
   ``` bin/console pimcore:tensorflow predict -N cars_country -m 0 -i 231 ```


## Credits

- [@poel22](https://github.com/poel22)
